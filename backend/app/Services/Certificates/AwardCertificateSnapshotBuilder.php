<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Enums\Certificates\CertificateKind;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Entities\Region;

/**
 * What an award certificate states, frozen at issue. It never holds a score, a percentage or the committee average:
 * those stay confidential (03 §8.4), so neither the PDF nor the public verification page can show them.
 */
final class AwardCertificateSnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Assessment $assessment, CertificateKind $kind): array
    {
        $cycle = $assessment->awardCycle;
        $ranking = $kind === CertificateKind::AdvancedParticipation ? null : $assessment->ranking;

        return [
            'entity_name' => $assessment->entity->name->toArray(),
            'entity_reference' => $assessment->entity->reference(),
            'request_number' => $assessment->request_number,
            'award' => [
                'cycle_name' => $cycle instanceof AwardCycle ? $cycle->name->toArray() : null,
                'cycle_year' => $cycle?->year,
                'region' => $kind !== CertificateKind::AdvancedParticipation && $assessment->region instanceof Region ? $assessment->region->name->toArray() : null,
                'regional_rank' => $ranking instanceof AwardRanking ? $ranking->regional_rank : null,
                'national_rank' => $ranking instanceof AwardRanking ? $ranking->national_rank : null,
            ],
        ];
    }
}
