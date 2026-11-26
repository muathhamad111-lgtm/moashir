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
use App\Services\Dashboards\RequestPipeline;
use App\Services\Identity\RequestPathScope;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use Illuminate\Support\Facades\Date;

final readonly class RequestQueueActionItems implements ActionItemSource
{
    public function __construct(private RequestPipeline $pipeline, private RequestPathScope $paths) {}

    public function isAvailableTo(User $user): bool
    {
        return $user->can(PermissionKey::RequestsQueueView->value);
    }

    public function itemsFor(User $user): array
    {
        $clock = [...AssessmentPath::Accredited->reviewClockStateValues(), ...AssessmentPath::Advanced->reviewClockStateValues()];
        $counts = AggregateRow::counts($this->pipeline->queue($this->paths->pathsFor($user)), [
            'awaiting_assignment' => ['status in (?, ?)', [Accredited::Submitted->value, Advanced::AwaitingReviewAssignment->value]],
            'overdue' => ['due_at < ? and status in ('.AggregateRow::placeholders($clock).')', [Date::now()->toIso8601String(), ...$clock]],
        ]);
        $items = [];

        if (($counts['awaiting_assignment'] ?? 0) > 0 && $user->can(PermissionKey::RequestsAssign->value)) {
            $items[] = new ActionItem('assign_requests', $counts['awaiting_assignment'], ActionSeverity::Warning, Links::requestsWithStatus(Accredited::Submitted->value, Advanced::AwaitingReviewAssignment->value));
        }

        if (($counts['overdue'] ?? 0) > 0) {
            $items[] = new ActionItem('follow_up_overdue_requests', $counts['overdue'], ActionSeverity::Danger, Links::overdueRequests());
        }

        return $items;
    }
}
