<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Enums\Contact\ContactInboxStatus;
use App\Models\Contact\ContactMessage;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

/**
 * The SQL side of the derived inbox status (ContactInboxStatus::derive): filter by one status, or
 * count every status, with conditions on the message's own columns only.
 */
final class ContactInboxStatusQuery
{
    private const string STATUS_SQL = <<<'SQL'
        CASE
            WHEN replied_at IS NULL AND assigned_to_user_id IS NULL THEN 'new'
            WHEN replied_at IS NULL THEN 'assigned'
            WHEN sender_last_message_at > replied_at THEN 'awaiting_reply'
            ELSE 'replied'
        END
        SQL;

    /**
     * @param  Builder<ContactMessage>  $query
     */
    public function constrain(Builder $query, ContactInboxStatus $status): void
    {
        match ($status) {
            ContactInboxStatus::New => $query->whereNull('replied_at')->whereNull('assigned_to_user_id'),
            ContactInboxStatus::Assigned => $query->whereNull('replied_at')->whereNotNull('assigned_to_user_id'),
            ContactInboxStatus::AwaitingReply => $query->whereNotNull('replied_at')->whereColumn('sender_last_message_at', '>', 'replied_at'),
            ContactInboxStatus::Replied => $query->whereNotNull('replied_at')->where(static fn (Builder $quiet): Builder => $quiet
                ->whereNull('sender_last_message_at')
                ->orWhereColumn('sender_last_message_at', '<=', 'replied_at')),
        };
    }

    /**
     * @param  Builder<ContactMessage>  $query
     * @return array<string, int>
     */
    public function counts(Builder $query): array
    {
        $totals = (clone $query)->toBase()->reorder()
            ->selectRaw(self::STATUS_SQL.' AS inbox_status, count(*) AS total')
            ->groupByRaw(self::STATUS_SQL)
            ->pluck('total', 'inbox_status');
        $counts = [];

        foreach (ContactInboxStatus::cases() as $status) {
            $counts[$status->value] = ScalarValue::int($totals->get($status->value));
        }

        return $counts;
    }
}
