<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\RequestQueueRow;
use App\Data\Assessments\SubmissionProgress;
use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;
use App\Support\Assessments\PaidDraftReminderWindow;
use App\Support\Assessments\RequestPaymentPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * @property-read RequestQueueRow $resource
 */
final class RequestQueueRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource->assessment;

        return [
            ...new RequestQueueItemResource($assessment)->toArray($request),
            'opened_at' => $assessment->created_at->toIso8601String(),
            'last_activity_at' => ($assessment->entity_activity_at ?? $assessment->created_at)->toIso8601String(),
            'progress' => $this->progressOf($this->resource->progress),
            'payment' => RequestPaymentPresenter::forStaff($assessment->payment, $request->user()),
            ...$this->reminderOf($request),
        ];
    }

    /**
     * @return array{current_period_ends_on: string|null, last_reminded_at: string|null, can_remind: bool, next_remind_at: string|null}
     */
    private function reminderOf(Request $request): array
    {
        $assessment = $this->resource->assessment;
        $viewer = $request->user();
        $canRemind = $viewer instanceof User
            && $viewer->can(PermissionKey::RequestsAssign->value)
            && PaidDraftReminderWindow::isPaidDraft($assessment)
            && PaidDraftReminderWindow::allowsReminderAt($assessment, Date::now());

        return [
            'current_period_ends_on' => $this->resource->currentPeriodEndsOn?->toDateString(),
            'last_reminded_at' => $assessment->paid_draft_reminded_at?->toIso8601String(),
            'can_remind' => $canRemind,
            'next_remind_at' => PaidDraftReminderWindow::nextRemindAt($assessment)?->toIso8601String(),
        ];
    }

    /**
     * @return array{subs_complete: int, subs_total: int}|null
     */
    private function progressOf(?SubmissionProgress $progress): ?array
    {
        return $progress instanceof SubmissionProgress ? ['subs_complete' => $progress->subsComplete, 'subs_total' => $progress->subsTotal] : null;
    }
}
