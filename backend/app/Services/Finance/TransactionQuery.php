<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\TransactionFilters;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder;

final readonly class TransactionQuery
{
    public function __construct(private Repository $config) {}

    /**
     * @return Builder<Payment>
     */
    public function filtered(TransactionFilters $filters): Builder
    {
        $from = $filters->from;
        $to = $filters->to;

        return Payment::query()
            ->where('mode', $filters->mode)
            ->when($filters->status instanceof PaymentStatus, static fn (Builder $query): Builder => $query->where('status', $filters->status))
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $query): Builder => $query->where('purpose', $filters->purpose))
            ->when($filters->method instanceof PaymentInstrument, static fn (Builder $query): Builder => $query->where('method', $filters->method))
            ->when($from instanceof CarbonImmutable, fn (Builder $query): Builder => $query->where('created_at', '>=', $this->startOf($from)))
            ->when($to instanceof CarbonImmutable, fn (Builder $query): Builder => $query->where('created_at', '<', $this->startOf($to)->addDay()))
            ->when($filters->search instanceof SearchTerm, static fn (Builder $query): Builder => self::matching($query, $filters->search));
    }

    /**
     * @return Builder<Payment>
     */
    public function sorted(TransactionFilters $filters): Builder
    {
        return $this->filtered($filters)
            ->orderBy($filters->sort->column(), $filters->sort->direction())
            ->orderBy('id', $filters->sort->direction());
    }

    private function startOf(?CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::parse(($date ?? CarbonImmutable::now())->toDateString(), $this->config->string('app.display_timezone'))->utc();
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    private static function matching(Builder $query, ?SearchTerm $term): Builder
    {
        if (! $term instanceof SearchTerm) {
            return $query;
        }

        $pattern = $term->containsPattern();
        $normalize = SearchTextNormalizer::DATABASE_FUNCTION;

        return $query->where(static fn (Builder $inner): Builder => $inner
            ->whereRaw('lower(reference) like ?', [$pattern])
            ->orWhereRaw('lower(coalesce(provider_payment_id, \'\')) like ?', [$pattern])
            ->orWhereRaw($normalize."(entity_name->>'ar') like ?", [$pattern])
            ->orWhereRaw($normalize."(coalesce(entity_name->>'en', '')) like ?", [$pattern])
            ->orWhereHas('receipt', static fn (Builder $receipt): Builder => $receipt->whereRaw('lower(coalesce(number, \'\')) like ?', [$pattern])));
    }
}
