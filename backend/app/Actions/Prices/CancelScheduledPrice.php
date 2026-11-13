<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Prices\PricesRejected;
use App\Models\Identity\User;
use App\Services\Prices\PriceAuditTrail;
use App\Services\Prices\PriceEditAuthority;
use App\Services\Prices\PriceVersions;
use App\Services\Prices\PriceVersionWriter;
use App\Services\Settings\SettingsLock;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CancelScheduledPrice
{
    private const string LOCK_SECTION = PriceVersionWriter::LOCK_SECTION;

    public function __construct(
        private SettingsLock $lock,
        private PriceVersions $versions,
        private PriceAuditTrail $trail,
        private PriceEditAuthority $authority,
    ) {}

    public function handle(User $actor, PaymentPurpose $purpose, ?string $reason): void
    {
        $this->authority->recordReason($actor, $reason);

        DB::transaction(function () use ($actor, $purpose): void {
            $this->lock->lock(self::LOCK_SECTION);
            $now = Date::now();
            $scheduled = $this->versions->scheduled($purpose, $now) ?? throw PricesRejected::noScheduledVersion();

            $scheduled->forceFill(['cancelled_at' => $now, 'cancelled_by_user_id' => $actor->id])->save();
            $this->trail->recordCancellation($scheduled);
        });

        $this->authority->afterChange($actor);
    }
}
