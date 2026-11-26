<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\AbandonedCartFilters;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\CheckoutIntent;
use App\Services\Finance\AbandonedCarts;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAbandonedCarts
{
    public function __construct(private AbandonedCarts $carts) {}

    /**
     * @return LengthAwarePaginator<int, CheckoutIntent>
     */
    public function handle(AbandonedCartFilters $filters): LengthAwarePaginator
    {
        return $this->carts->query()
            ->with(['entity.owner', 'entity.region'])
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $query): Builder => $query->where('purpose', $filters->purpose))
            ->when($filters->contacted === true, static fn (Builder $query): Builder => $query->whereNotNull('contacted_at'))
            ->when($filters->contacted === false, static fn (Builder $query): Builder => $query->whereNull('contacted_at'))
            ->when($filters->search instanceof SearchTerm, static fn (Builder $query): Builder => self::matching($query, $filters->search))
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage);
    }

    /**
     * @param  Builder<CheckoutIntent>  $query
     * @return Builder<CheckoutIntent>
     */
    private static function matching(Builder $query, ?SearchTerm $term): Builder
    {
        if (! $term instanceof SearchTerm) {
            return $query;
        }

        $normalize = SearchTextNormalizer::DATABASE_FUNCTION;
        $pattern = $term->containsPattern();

        return $query->whereHas('entity', static fn (Builder $entity): Builder => $entity->where(static fn (Builder $inner): Builder => $inner
            ->whereRaw($normalize."(name->>'ar') like ?", [$pattern])
            ->orWhereRaw($normalize."(coalesce(name->>'en', '')) like ?", [$pattern])));
    }
}
