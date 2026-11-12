<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\Payment;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final class PaymentSearch
{
    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public static function forEntity(Builder $query, SearchTerm $term): Builder
    {
        $pattern = $term->containsPattern();

        return $query->where(static fn (Builder $inner): Builder => self::ownFields($inner, $pattern));
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public static function forStaff(Builder $query, SearchTerm $term): Builder
    {
        $pattern = $term->containsPattern();
        $normalize = SearchTextNormalizer::DATABASE_FUNCTION;

        return $query->where(static fn (Builder $inner): Builder => self::ownFields($inner, $pattern)
            ->orWhereRaw('lower(coalesce(provider_payment_id, \'\')) like ?', [$pattern])
            ->orWhereRaw($normalize."(entity_name->>'ar') like ?", [$pattern])
            ->orWhereRaw($normalize."(coalesce(entity_name->>'en', '')) like ?", [$pattern]));
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    private static function ownFields(Builder $query, string $pattern): Builder
    {
        $normalize = SearchTextNormalizer::DATABASE_FUNCTION;

        return $query
            ->whereRaw('lower(reference) like ?', [$pattern])
            ->orWhereHas('receipt', static fn (Builder $receipt): Builder => $receipt->whereRaw('lower(coalesce(number, \'\')) like ?', [$pattern]))
            ->orWhereHas('initiatedBy', static fn (Builder $user): Builder => $user->whereRaw($normalize.'(name) like ?', [$pattern]));
    }
}
