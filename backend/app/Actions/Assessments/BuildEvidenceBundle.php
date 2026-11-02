<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\EvidenceBundleStatus;
use App\Events\Assessments\EvidenceBundleReady;
use App\Models\Assessments\EvidenceBundle;
use App\Services\Assessments\EvidenceBundleArchiver;
use App\Services\Assessments\EvidenceReader;
use App\Services\Files\StoredFileRemover;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class BuildEvidenceBundle
{
    public function __construct(
        private EvidenceReader $evidence,
        private EvidenceBundleArchiver $archiver,
        private StoredFileRemover $remover,
        private Repository $config,
    ) {}

    public function handle(int $bundleId): ?EvidenceBundle
    {
        $bundle = EvidenceBundle::query()->with('assessment')->find($bundleId);

        if (! $bundle instanceof EvidenceBundle || $bundle->status !== EvidenceBundleStatus::Preparing) {
            return $bundle;
        }

        $files = $this->evidence->cleanOnly($bundle->assessment);

        if ($files === []) {
            $this->markFailed($bundle->id);

            return $bundle->refresh();
        }

        $stored = $this->archiver->archive($bundle, $files);

        try {
            DB::transaction(function () use ($bundle, $stored, $files): void {
                $bundle->forceFill([
                    'status' => EvidenceBundleStatus::Ready,
                    'stored_file_id' => $stored->id,
                    'file_count' => count($files),
                    'size_bytes' => $stored->size_bytes,
                    'ready_at' => Date::now(),
                    'expires_at' => Date::now()->addHours($this->config->integer('moashir.assessment.evidence_bundle_ttl_hours')),
                ])->save();
            });
        } catch (Throwable $failure) {
            $this->remover->remove($stored);

            throw $failure;
        }

        EvidenceBundleReady::dispatch($bundle);

        return $bundle;
    }

    public function markFailed(int $bundleId): void
    {
        EvidenceBundle::query()->whereKey($bundleId)->where('status', EvidenceBundleStatus::Preparing)->update(['status' => EvidenceBundleStatus::Failed]);
    }
}
