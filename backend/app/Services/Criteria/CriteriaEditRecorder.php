<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Contracts\Criteria\OpenAssessmentCounter;
use App\Data\Criteria\CriteriaChange;
use App\Data\Criteria\CriteriaEditRecord;
use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Notifications\NotificationCode;
use App\Events\Criteria\CriteriaEditApplied;
use App\Models\Criteria\CriteriaEditEvent;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;

final readonly class CriteriaEditRecorder
{
    public function __construct(
        private OpenAssessmentCounter $openAssessments,
        private CriteriaAuditTrail $audit,
        private RequestContext $context,
        private SecurityAlerter $alerter,
    ) {}

    public function record(User $actor, CriteriaEditRecord $record): CriteriaEditEvent
    {
        $this->context->recordReason($record->reason);

        $editEvent = CriteriaEditEvent::query()->create([
            'kind' => $record->kind,
            'scope' => $record->scope,
            'from_version_id' => $record->from->id,
            'to_version_id' => $record->to?->id,
            'changes' => array_map(static fn (CriteriaChange $change): array => $change->toArray(), $record->changes),
            'affected_open_count' => $record->effect()->rebindsOpenAssessments() ? $this->openAssessments->countBoundTo($record->from) : 0,
            'reason' => $record->reason,
            'edited_by_user_id' => $actor->id,
        ]);

        $this->audit->record($this->auditEventFor($record), $record->to ?? $record->from, [], [
            'edit_event_id' => $editEvent->id,
            ...$record->effect()->toArray(),
            'from_version_id' => $record->from->id,
            'to_version_id' => $record->to?->id,
            'change_count' => count($record->changes),
            'affected_open_count' => $editEvent->affected_open_count,
        ]);

        CriteriaEditApplied::dispatch($editEvent, $record->changes, $actor->id);
        $this->alertWhenSuperAdmin($actor);

        return $editEvent;
    }

    private function auditEventFor(CriteriaEditRecord $record): AuditEvent
    {
        return $record->kind === CriteriaEditKind::TextCorrection
            ? AuditEvent::CriteriaVersionLabelsCorrected
            : AuditEvent::CriteriaEditedWithScope;
    }

    private function alertWhenSuperAdmin(User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }
}
