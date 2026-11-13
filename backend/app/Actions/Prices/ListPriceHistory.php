<?php

declare(strict_types=1);

namespace App\Actions\Prices;

use App\Data\Prices\PriceHistoryItem;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\ServicePriceVersion;
use App\Services\Prices\PriceVersions;
use App\Support\Prices\PriceVersionPresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;

final readonly class ListPriceHistory
{
    private const array RELATIONS = ['setBy', 'cancelledBy', 'previousVersion'];

    public function __construct(private PriceVersions $versions) {}

    /**
     * @return LengthAwarePaginator<int, PriceHistoryItem>
     */
    public function handle(PaymentPurpose $purpose, int $perPage): LengthAwarePaginator
    {
        $now = Date::now();
        $inForceId = $this->versions->inForce($purpose, $now)?->id;

        return ServicePriceVersion::query()
            ->of($purpose)
            ->with(self::RELATIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(static fn (ServicePriceVersion $version): PriceHistoryItem => new PriceHistoryItem(
                $version,
                PriceVersionPresenter::stateOf($version, $inForceId, $now),
            ));
    }
}
