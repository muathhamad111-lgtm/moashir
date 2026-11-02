<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\AdvancedEligibility;
use App\Data\Award\EligibilityCheck;
use App\Enums\Award\EligibilityRule;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;

final readonly class AdvancedEligibilityChecker
{
    public function __construct(private Repository $config) {}

    public function check(Entity $entity, ?AwardCycle $cycle, CarbonInterface $now): AdvancedEligibility
    {
        if (! $cycle instanceof AwardCycle) {
            return new AdvancedEligibility(null, false, []);
        }

        return new AdvancedEligibility($cycle, $cycle->acceptsApplicationsAt($now), [
            $this->ageCheck($entity, $cycle, $now),
            $this->regionCheck($entity, $cycle),
        ]);
    }

    public function entityAgeAt(Entity $entity, CarbonInterface $now): int
    {
        return max(0, $now->copy()->setTimezone($this->config->string('app.display_timezone'))->year - $entity->founding_year);
    }

    private function ageCheck(Entity $entity, AwardCycle $cycle, CarbonInterface $now): EligibilityCheck
    {
        $age = $this->entityAgeAt($entity, $now);

        return new EligibilityCheck(EligibilityRule::MinEntityAge, $age >= $cycle->min_entity_age_years, $cycle->min_entity_age_years, $age);
    }

    private function regionCheck(Entity $entity, AwardCycle $cycle): EligibilityCheck
    {
        return new EligibilityCheck(EligibilityRule::RegionCovered, $cycle->covers($entity->region_id), null, null);
    }
}
