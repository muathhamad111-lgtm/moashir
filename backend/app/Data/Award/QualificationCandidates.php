<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Assessments\Assessment;

/**
 * The requests awaiting filtering whose initial result reaches the cycle threshold, minus the ones the actor
 * may not decide because they assessed them (segregation of duties).
 */
final readonly class QualificationCandidates
{
    /**
     * @param  list<Assessment>  $eligible
     */
    public function __construct(public array $eligible, public int $ownAssessments) {}
}
