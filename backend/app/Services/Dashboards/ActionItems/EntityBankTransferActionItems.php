<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Data\Dashboards\ActionItemContext;
use App\Enums\Dashboards\ActionSeverity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\ScalarValue;

final class EntityBankTransferActionItems implements ActionItemSource
{
    private const string AGGREGATES = <<<'SQL'
        count(*) filter (where status = 'under_review') as under_review,
        min(public_id::text) filter (where status = 'under_review') as under_review_id,
        min(reference) filter (where status = 'under_review') as under_review_reference,
        count(*) filter (where status = 'rejected' and not exists (select 1 from payments as newer where newer.entity_id = payments.entity_id and newer.purpose = payments.purpose and newer.id > payments.id)) as rejected,
        min(public_id::text) filter (where status = 'rejected' and not exists (select 1 from payments as newer where newer.entity_id = payments.entity_id and newer.purpose = payments.purpose and newer.id > payments.id)) as rejected_id,
        min(reference) filter (where status = 'rejected' and not exists (select 1 from payments as newer where newer.entity_id = payments.entity_id and newer.purpose = payments.purpose and newer.id > payments.id)) as rejected_reference
        SQL;

    public function isAvailableTo(User $user): bool
    {
        return $user->entity_id !== null;
    }

    public function itemsFor(User $user): array
    {
        $row = AggregateRow::fetch(Payment::query()->ownedBy((int) $user->entity_id)->bankTransfers(), self::AGGREGATES);

        return array_values(array_filter([
            $this->item('bank_transfer_under_review', ActionSeverity::Info, $row, 'under_review'),
            $this->item('bank_transfer_rejected', ActionSeverity::Danger, $row, 'rejected'),
        ], static fn (ActionItem $item): bool => $item->count > 0));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function item(string $key, ActionSeverity $severity, array $row, string $column): ActionItem
    {
        $count = ScalarValue::int($row[$column] ?? null);
        $context = $count === 1
            ? ActionItemContext::forPayment(ScalarValue::string($row[$column.'_id'] ?? null), ScalarValue::string($row[$column.'_reference'] ?? null))
            : ActionItemContext::none();

        return new ActionItem($key, $count, $severity, Links::MY_PAYMENTS, null, $context);
    }
}
