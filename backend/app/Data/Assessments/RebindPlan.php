<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Assessments\AssessmentScore;

final readonly class RebindPlan
{
    /**
     * @param  list<CarriedScore>  $carried
     * @param  list<AssessmentScore>  $cleared
     */
    public function __construct(public array $carried, public array $cleared) {}

    /**
     * @return list<string>
     */
    public function carriedCodes(): array
    {
        $codes = array_map(static fn (CarriedScore $carried): string => $carried->target->code, $this->carried);
        sort($codes);

        return $codes;
    }
}
