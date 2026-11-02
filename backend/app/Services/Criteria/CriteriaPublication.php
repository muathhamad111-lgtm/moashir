<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\CriteriaChange;
use App\Data\Criteria\CriteriaEditRecord;
use App\Data\Criteria\PublishInstruction;
use App\Data\Criteria\PublishOutcome;
use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Events\Criteria\CriteriaVersionPublished;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;

final readonly class CriteriaPublication
{
    public function __construct(
        private CriteriaTreeLoader $loader,
        private CriteriaVersionDiff $diff,
        private CriteriaVersionPublisher $publisher,
        private CriteriaEditRecorder $edits,
        private CriteriaAuditTrail $audit,
    ) {}

    public function publish(?User $actor, CriteriaVersion $draft, ?CriteriaVersion $previous, PublishInstruction $instruction): PublishOutcome
    {
        $changes = $previous instanceof CriteriaVersion ? $this->diff->between($this->loader->load($previous), $draft) : [];

        $this->publisher->publish($draft, $previous, $actor);
        $this->audit->record(AuditEvent::CriteriaVersionPublished, $draft, ['status' => 'draft'], [
            'status' => $draft->status->value,
            'scale_kind' => $draft->scale_kind?->value,
            'previous_version_id' => $previous?->id,
        ]);

        $record = $this->editRecord($draft, $previous, $instruction, $changes);
        $editEvent = $record instanceof CriteriaEditRecord && $actor instanceof User ? $this->edits->record($actor, $record) : null;

        CriteriaVersionPublished::dispatch($draft, $previous, $actor?->id);

        return new PublishOutcome($draft, $editEvent, $record?->effect(), count($changes));
    }

    /**
     * @param  list<CriteriaChange>  $changes
     */
    private function editRecord(CriteriaVersion $draft, ?CriteriaVersion $previous, PublishInstruction $instruction, array $changes): ?CriteriaEditRecord
    {
        if (! $previous instanceof CriteriaVersion) {
            return null;
        }

        return new CriteriaEditRecord(
            CriteriaEditKind::Structural,
            $instruction->scope ?? CriteriaEditScope::NewOnly,
            $previous,
            $draft,
            $changes,
            $instruction->reason,
        );
    }
}
