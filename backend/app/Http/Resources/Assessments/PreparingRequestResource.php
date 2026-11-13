<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\PreparingRequest;
use App\Data\Assessments\SubmissionProgress;
use App\Enums\Identity\PermissionKey;
use App\Models\Identity\User;
use App\Support\Assessments\PaidDraftReminderWindow;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\RequestPaymentPresenter;
use App\Support\Assessments\StatusMask;
use App\Support\Award\CyclePresenter;
use App\Support\Certificates\EntityLevelPresenter;
use App\Support\Entities\EntityPresenter;
use App\Support\Periods\PeriodPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * @property-read PreparingRequest $resource
 */
final class PreparingRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource->assessment;

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            ...PathPresenter::of($assessment->path),
            'status' => StatusMask::statusFor($assessment, $request->user()),
            'is_frozen' => $assessment->isFrozen(),
            'entity' => [...EntityPresenter::summary($assessment->entity), 'level' => EntityLevelPresenter::levelOf($assessment->entity)?->value],
            'award_cycle' => CyclePresenter::summary($assessment->awardCycle),
            'opened_at' => $assessment->created_at->toIso8601String(),
            'payment' => RequestPaymentPresenter::of($assessment->payment, $request->user()),
            'progress' => $this->progressOf($this->resource->progress),
            'last_activity_at' => ($assessment->entity_activity_at ?? $assessment->created_at)->toIso8601String(),
            ...PeriodPresenter::attribution($assessment),
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
