<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\EvidenceDestination;
use App\Data\Assessments\EvidenceUpload;
use App\Data\Assessments\StoredEvidence;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\EvidenceStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\EvidenceFileType;
use App\Enums\Files\StoredFilePurpose;
use App\Events\Assessments\AccreditedDraftSaved;
use App\Jobs\Files\ScanStoredFileJob;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Assessments\EntityContentGate;
use App\Services\Assessments\EvidenceTarget;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UploadEvidence
{
    private const string FILE_FIELD = 'file';

    public function __construct(
        private EntityContentGate $gate,
        private EvidenceTarget $target,
        private FileIntake $files,
        private StoredFileRemover $remover,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, EvidenceUpload $upload): StoredEvidence
    {
        $this->gate->assertOpenFor($assessment);
        $destination = $this->target->resolve($assessment, $upload);

        $stored = $this->files->acceptForBackgroundScan(
            $upload->file,
            StoredFilePurpose::AssessmentEvidence,
            $actor,
            self::FILE_FIELD,
            EvidenceFileType::uploadTypesOf($destination->types),
        );

        try {
            return DB::transaction(fn (): StoredEvidence => $this->attach($actor, $assessment, $upload, $stored));
        } catch (Throwable $failure) {
            $this->remover->remove($stored);

            throw $failure;
        }
    }

    private function attach(User $actor, Assessment $assessment, EvidenceUpload $upload, StoredFile $stored): StoredEvidence
    {
        $locked = $this->gate->enter($actor, $assessment, $upload->reason);
        $subs = $this->target->subsOf($locked);
        $destination = $this->target->resolve($locked, $upload);
        $node = $destination->node;

        $evidence = EvidenceFile::query()->create([
            'assessment_id' => $locked->id,
            EvidenceFile::ownerColumn($node->level()) => $node->nodeCode(),
            'attachment_key' => $destination->slot?->key,
            'stored_file_id' => $stored->id,
            'status' => EvidenceStatus::Uploaded,
            'uploaded_by_user_id' => $actor->id,
        ]);
        $stored->attachable()->associate($evidence)->save();
        $evidence->setRelation('storedFile', $stored);
        $locked->touch();

        $this->recordUpload($locked, $evidence, $destination);
        ScanStoredFileJob::dispatch($stored->id)->afterCommit();
        AccreditedDraftSaved::dispatch($locked);

        return new StoredEvidence($evidence, $subs);
    }

    private function recordUpload(Assessment $locked, EvidenceFile $evidence, EvidenceDestination $destination): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::EvidenceUploaded, $locked, [], [
            'evidence_id' => $evidence->public_id,
            'level' => $destination->node->level()->value,
            EvidenceFile::ownerColumn($destination->node->level()) => $destination->node->nodeCode(),
            'attachment_key' => $evidence->attachment_key,
            'stored_file_id' => $evidence->stored_file_id,
        ]));
    }
}
