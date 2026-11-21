<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Assessor;

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Certificates\CertificateLevel;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Identity\User;
use App\Services\Certificates\CertificateSettingsReader;

/**
 * How the accredited results an assessor's reviews produced in a period spread over
 * the certificate levels, read with the current level thresholds.
 */
final readonly class AssessorLevels
{
    /** A result below the lowest level's minimum. */
    public const string BELOW_LEVELS = 'none';

    public function __construct(private AssessorReviews $reviews, private CertificateSettingsReader $certificates) {}

    /**
     * @return array<string, int> every level, highest first, then results below the levels
     */
    public function given(User $assessor, ReportingPeriod $period): array
    {
        $thresholds = $this->certificates->thresholds();
        $counts = array_fill_keys([...array_reverse(CertificateLevel::values()), self::BELOW_LEVELS], 0);

        AssessmentResultSnapshot::query()
            ->whereIn('id', $this->reviews->accreditedResultsWithin($assessor, $period)->whereNotNull('current_result_snapshot_id')->select('current_result_snapshot_id'))
            ->get(['id', 'percent'])
            ->each(static function (AssessmentResultSnapshot $snapshot) use ($thresholds, &$counts): void {
                $counts[$thresholds->levelFor($snapshot->percent)->value ?? self::BELOW_LEVELS]++;
            });

        return $counts;
    }
}
