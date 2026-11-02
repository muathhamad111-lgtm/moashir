<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\SecretaryRequestFilters;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Award\AwardCycle;
use App\Services\Award\SecretaryRequestsQuery;
use App\Support\ScalarValue;

final readonly class SummarizeSecretaryRequests
{
    public function __construct(private SecretaryRequestsQuery $requests) {}

    /**
     * @return array<string, int>
     */
    public function handle(AwardCycle $cycle, SecretaryRequestFilters $filters): array
    {
        $counts = $this->requests->filtered($cycle, $filters->withoutStatus())->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $byStatus = [];

        foreach ([...AdvancedAssessmentStatus::committeeStates(), ...AdvancedAssessmentStatus::resultStates()] as $status) {
            $byStatus[$status->value] = ScalarValue::int($counts->get($status->value));
        }

        return $byStatus;
    }
}
