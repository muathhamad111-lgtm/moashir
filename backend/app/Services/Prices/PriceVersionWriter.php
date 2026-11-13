<?php

declare(strict_types=1);

namespace App\Services\Prices;

use App\Data\Prices\PriceChange;
use App\Data\Prices\PriceChangeOutcome;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Prices\PricesRejected;
use App\Models\Finance\ServicePriceVersion;
use App\Models\Identity\User;
use App\Services\Settings\SettingsLock;
use App\Support\Prices\PriceRowPresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class PriceVersionWriter
{
    public const string LOCK_SECTION = 'prices';

    public function __construct(
        private SettingsLock $lock,
        private PriceVersions $versions,
        private PriceRowReader $rows,
        private PriceAuditTrail $trail,
    ) {}

    public function write(User $actor, PaymentPurpose $purpose, PriceChange $change): PriceChangeOutcome
    {
        $this->lock->lock(self::LOCK_SECTION);
        $now = Date::now();
        $inForce = $this->versions->inForce($purpose, $now);

        if ($inForce?->id !== $change->baseVersionId) {
            throw PricesRejected::staleVersion(PriceRowPresenter::present($this->rows->read($purpose), $this->rows->timezone()));
        }

        if ($change->isImmediate() && $inForce?->matches($change->price, $change->availability) === true) {
            return new PriceChangeOutcome(false, null);
        }

        $cancelled = $this->cancelScheduled($actor, $purpose, $now);
        $created = ServicePriceVersion::query()->create([
            'purpose' => $purpose,
            'price_before_vat' => $change->price->amount,
            'availability' => $change->availability,
            'effective_from' => $change->effectiveFrom ?? $now,
            'previous_version_id' => $inForce?->id,
            'set_by_user_id' => $actor->id,
        ]);
        $this->trail->recordChange($created, $inForce, $cancelled);

        return new PriceChangeOutcome(true, $cancelled?->id);
    }

    private function cancelScheduled(User $actor, PaymentPurpose $purpose, CarbonInterface $now): ?ServicePriceVersion
    {
        $scheduled = $this->versions->scheduled($purpose, $now);

        $scheduled?->forceFill(['cancelled_at' => $now, 'cancelled_by_user_id' => $actor->id])->save();

        return $scheduled;
    }
}
