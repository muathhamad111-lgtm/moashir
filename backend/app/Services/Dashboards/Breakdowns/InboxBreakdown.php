<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Models\Contact\ContactMessage;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\PeriodWindow;
use App\Support\ScalarValue;

final readonly class InboxBreakdown implements OverviewBreakdownSource
{
    private const string FIGURES = 'count(*) filter (where created_at >= ? and created_at < ?) as received, '
        .'count(*) filter (where created_at >= ? and created_at < ? and status in (?, ?)) as awaiting_reply, '
        .'count(*) filter (where replied_at >= ? and replied_at < ?) as replied, '
        .'round(cast(avg(extract(epoch from replied_at - created_at) / 3600) filter (where replied_at >= ? and replied_at < ?) as numeric), 1) as average_reply_hours';

    public function keys(): array
    {
        return [OverviewBreakdownKey::Inbox];
    }

    /**
     * @return array{inbox: array{received: int, awaiting_reply: int, replied: int, average_reply_hours: float|null}}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $window = PeriodWindow::bindings($period);
        $row = AggregateRow::fetch(ContactMessage::query(), self::FIGURES, [
            ...$window,
            ...$window, ContactMessageStatus::New->value, ContactMessageStatus::InProgress->value,
            ...$window,
            ...$window,
        ]);
        $average = $row['average_reply_hours'] ?? null;

        return [OverviewBreakdownKey::Inbox->value => [
            'received' => ScalarValue::int($row['received'] ?? null),
            'awaiting_reply' => ScalarValue::int($row['awaiting_reply'] ?? null),
            'replied' => ScalarValue::int($row['replied'] ?? null),
            'average_reply_hours' => is_numeric($average) ? (float) $average : null,
        ]];
    }
}
