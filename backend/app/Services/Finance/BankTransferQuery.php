<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\BankTransferFilters;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Finance\Payment;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\Search\SearchTextNormalizer;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

final readonly class BankTransferQuery
{
    public const array RELATIONS = [
        'entity.owner', 'receipt', 'bankTransfer.bankAccount', 'bankTransfer.proofFile', 'bankTransfer.reviewedBy', 'awardCycle',
    ];

    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<Payment>
     */
    public function filtered(BankTransferFilters $filters): Builder
    {
        $query = Payment::query()
            ->bankTransfers()
            ->when($filters->status instanceof PaymentStatus, static fn (Builder $inner): Builder => $inner->where('status', $filters->status))
            ->when($filters->purpose instanceof PaymentPurpose, static fn (Builder $inner): Builder => $inner->where('purpose', $filters->purpose))
            ->when($filters->mode instanceof IntegrationMode, static fn (Builder $inner): Builder => $inner->where('mode', $filters->mode))
            ->when($filters->bankAccountId !== null, static fn (Builder $inner): Builder => self::toAccount($inner, (string) $filters->bankAccountId))
            ->when($filters->search instanceof SearchTerm, static fn (Builder $inner): Builder => self::matching($inner, $filters->search));

        return $filters->frozenEntities ? $this->onlyFrozen($query) : $this->freezeGuard->excludeFrozen($query);
    }

    /**
     * @return Builder<Payment>
     */
    public function sorted(BankTransferFilters $filters): Builder
    {
        return $this->filtered($filters)
            ->orderBy('created_at', $filters->sort->direction())
            ->orderBy('id', $filters->sort->direction());
    }

    public function awaitingReviewCount(): int
    {
        return $this->freezeGuard->excludeFrozen(Payment::query()->bankTransfers()->where('status', PaymentStatus::UnderReview))->count();
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    private function onlyFrozen(Builder $query): Builder
    {
        return $query->whereIn('entity_id', static fn (QueryBuilder $frozen): QueryBuilder => $frozen
            ->select('id')
            ->from('entities')
            ->whereNotNull('deleted_at'));
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    private static function toAccount(Builder $query, string $bankAccountId): Builder
    {
        return $query->whereHas('bankTransfer', static fn (Builder $transfer): Builder => $transfer
            ->whereHas('bankAccount', static fn (Builder $account): Builder => $account->where('public_id', $bankAccountId)));
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
            ->orWhereRaw($normalize."(entity_name->>'ar') like ?", [$pattern])
            ->orWhereRaw($normalize."(coalesce(entity_name->>'en', '')) like ?", [$pattern]));
    }
}
