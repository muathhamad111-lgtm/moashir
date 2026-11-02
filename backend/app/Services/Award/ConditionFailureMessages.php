<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\AdvancedEligibility;
use App\Data\Award\EligibilityCheck;

final class ConditionFailureMessages
{
    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(AdvancedEligibility $eligibility): array
    {
        return ['conditions' => array_map(static fn (EligibilityCheck $check): string => __('errors.advanced.conditions.'.$check->rule->value, [
            'required' => (string) $check->required,
            'actual' => (string) $check->actual,
        ]), $eligibility->failures())];
    }
}
