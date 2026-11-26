<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\ReviewNoteChanges;
use App\Data\Assessments\ReviewNotesView;
use App\Data\Assessments\ReviewNoteUnit;
use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\ReviewNotesGate;
use App\Services\Assessments\ReviewNoteSheetWriter;
use App\Services\Assessments\ReviewNotesViewer;
use App\Services\Assessments\ReviewNoteUnitGuard;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SaveReviewNotes
{
    public function __construct(
        private ReviewNotesGate $gate,
        private ReviewNoteUnitGuard $units,
        private ReviewNoteSheetWriter $writer,
        private ReviewNotesViewer $viewer,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, ReviewNoteChanges $changes, ?string $reason): ReviewNotesView
    {
        return DB::transaction(function () use ($actor, $assessment, $changes, $reason): ReviewNotesView {
            $locked = $this->gate->enter($actor, $assessment, $reason);
            $this->units->assertWithin($locked->criteriaVersion, $changes);
            $changed = $this->writer->write($locked, $changes, $actor);

            if ($changed !== []) {
                $this->audit->record(new AuditEntry(AuditEvent::AssessmentReviewNotesSaved, $locked, [], [
                    'changed' => count($changed),
                    'levels' => $this->levelsOf($changed),
                ]));
            }

            return $this->viewer->for($actor, $locked);
        });
    }

    /**
     * @param  list<ReviewNoteUnit>  $units
     * @return list<string>
     */
    private function levelsOf(array $units): array
    {
        return array_values(array_unique(array_map(static fn (ReviewNoteUnit $unit): string => $unit->level->value, $units)));
    }
}
