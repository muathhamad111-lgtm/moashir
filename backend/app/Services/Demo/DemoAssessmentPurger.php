<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Platform\DemoPurgeCount;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Certificates\CertificateVerificationLog;
use App\Support\Demo\DemoPurgeTally;
use App\Support\ScalarValue;

final readonly class DemoAssessmentPurger
{
    public function __construct(private DemoFiles $files) {}

    /**
     * @param  list<int>  $assessmentIds
     * @return list<int>
     */
    public function purge(array $assessmentIds, DemoPurgeTally $tally): array
    {
        if ($assessmentIds === []) {
            return [];
        }

        $fileIds = $this->files->ofAssessments($assessmentIds);
        $tally->add(DemoPurgeCount::Certificates, $this->purgeCertificates($assessmentIds));
        $tally->add(DemoPurgeCount::Assessments, ScalarValue::int(Assessment::query()->whereKey($assessmentIds)->toBase()->delete()));

        return $fileIds;
    }

    /**
     * @param  list<int>  $assessmentIds
     */
    private function purgeCertificates(array $assessmentIds): int
    {
        $certificateIds = array_values(array_map(
            static fn (mixed $id): int => ScalarValue::int($id),
            Certificate::query()->whereIn('assessment_id', $assessmentIds)->orderByDesc('id')->pluck('id')->all(),
        ));

        CertificateVerificationLog::query()->whereIn('certificate_id', $certificateIds)->toBase()->delete();

        foreach ($certificateIds as $certificateId) {
            Certificate::query()->whereKey($certificateId)->toBase()->delete();
        }

        return count($certificateIds);
    }
}
