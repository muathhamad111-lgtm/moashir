<?php

declare(strict_types=1);

namespace App\Services\Dashboards\ActionItems;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Enums\Assessments\AccreditedAssessmentStatus as Accredited;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\ActionSeverity;
use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\ScalarValue;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class AssessorActionItems implements ActionItemSource
{
    public function __construct(private AssessmentTotals $assessments) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::RequestsAssignedView->value) && $user->can(PermissionKey::AssessmentScoreManage->value);
    }

    public function itemsFor(User $user): array
    {
        $now = Date::now()->toIso8601String();
        $clock = [...AssessmentPath::Accredited->reviewClockStateValues(), ...AssessmentPath::Advanced->reviewClockStateValues()];
        $placeholders = AggregateRow::placeholders($clock);
        $row = AggregateRow::fetch($this->assessments->operational()->assignedTo($user->id), 'count(*) filter (where status = ?) as to_start, '
            .'count(*) filter (where status in (?, ?)) as in_review, '
            .'count(*) filter (where due_at < ? and status in ('.$placeholders.')) as overdue, '
            .'min(due_at) filter (where status in ('.$placeholders.')) as next_due', [
                Accredited::Assigned->value,
                Accredited::UnderReview->value, Advanced::UnderInitialAssessment->value,
                $now, ...$clock,
                ...$clock,
            ]);
        $nextDue = is_string($row['next_due'] ?? null) ? Date::parse($row['next_due']) : null;

        return array_values(array_filter([
            $this->item('start_reviews', ScalarValue::int($row['to_start'] ?? null), ActionSeverity::Warning, $nextDue),
            $this->item('continue_reviews', ScalarValue::int($row['in_review'] ?? null), ActionSeverity::Info, $nextDue),
            $this->item('overdue_reviews', ScalarValue::int($row['overdue'] ?? null), ActionSeverity::Danger, null),
        ]));
    }

    private function item(string $key, int $count, ActionSeverity $severity, ?CarbonInterface $dueAt): ?ActionItem
    {
        return $count > 0 ? new ActionItem($key, $count, $severity, Links::ASSIGNED_REQUESTS, $dueAt) : null;
    }
}
