<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\EvidenceBundleStatus;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Jobs\Assessments\BuildEvidenceBundleJob;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Identity\User;
use App\Services\Assessments\EvidenceReader;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Contracts\Config\Repository;

final readonly class RequestEvidenceBundle
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private EvidenceReader $evidence,
        private BuildEvidenceBundle $build,
        private AuditRecorder $audit,
        private Repository $config,
    ) {}

    public function handle(User $actor, Assessment $assessment): EvidenceBundle
    {
        $this->freezeGuard->assertNotFrozen($assessment->entity);
        $files = $this->evidence->cleanOnly($assessment);

        if ($files === []) {
            throw AssessmentRejected::bundleEmpty();
        }

        $size = array_sum(array_map(static fn (EvidenceFile $file): int => $file->storedFile->size_bytes, $files));
        $bundle = EvidenceBundle::query()->create([
            'assessment_id' => $assessment->id,
            'requested_by_user_id' => $actor->id,
            'status' => EvidenceBundleStatus::Preparing,
            'file_count' => count($files),
            'size_bytes' => $size,
        ]);
        $bundle->setRelation('assessment', $assessment);

        $this->audit->record(new AuditEntry(AuditEvent::EvidenceBundleRequested, $assessment, [], [
            'bundle_id' => $bundle->public_id,
            'file_count' => $bundle->file_count,
            'size_bytes' => $size,
        ]));

        if ($size > $this->config->integer('moashir.assessment.evidence_bundle_sync_max_bytes')) {
            BuildEvidenceBundleJob::dispatch($bundle->id);

            return $bundle;
        }

        return $this->build->handle($bundle->id) ?? $bundle;
    }
}
