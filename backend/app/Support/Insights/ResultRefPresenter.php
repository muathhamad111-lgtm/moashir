<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\InsightsRules;
use App\Data\Insights\ResultCandidate;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;

final class ResultRefPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(ResultCandidate $candidate): array
    {
        $snapshot = $candidate->snapshot();

        return [
            'assessment_id' => $candidate->assessment->id,
            'number' => $candidate->assessment->request_number,
            'percent' => $snapshot->percent->toDisplay(),
            'snapshot_version' => $snapshot->version,
            'verified_at' => $candidate->verifiedAt()?->toIso8601String(),
            'criteria_version' => self::versionOf($candidate->criteriaVersion),
            'certificate' => self::certificateOf($candidate->certificate),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function presentOptional(?ResultCandidate $candidate): ?array
    {
        return $candidate instanceof ResultCandidate ? self::present($candidate) : null;
    }

    /**
     * @return array{gap_thresholds_version: int, maturity_bands_version: int, effective_since: string|null}
     */
    public static function rules(InsightsRules $rules): array
    {
        return [
            'gap_thresholds_version' => $rules->gaps->version,
            'maturity_bands_version' => $rules->bands->version,
            'effective_since' => $rules->effectiveSince?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function versionOf(?CriteriaVersion $version): ?array
    {
        return $version instanceof CriteriaVersion
            ? ['id' => $version->id, 'version_number' => $version->version_number, 'label' => $version->label?->toArray()]
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function certificateOf(?Certificate $certificate): ?array
    {
        return $certificate instanceof Certificate
            ? ['id' => $certificate->public_id, 'number' => $certificate->number, 'status' => $certificate->status->value, 'level' => $certificate->level?->value]
            : null;
    }
}
