<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\GapClass;
use App\Support\ValueObjects\GapThresholds;

final readonly class StrengthsGaps
{
    /**
     * @param  list<ClassifiedMain>  $mains
     * @param  list<ClassifiedSub>  $strengths
     * @param  list<ClassifiedSub>  $opportunities
     * @param  list<ClassifiedSub>  $criticalGaps
     */
    public function __construct(
        public GapThresholds $rules,
        public array $mains,
        public array $strengths,
        public array $opportunities,
        public array $criticalGaps,
    ) {}

    /**
     * @return array<value-of<GapClass>, int>
     */
    public function counts(): array
    {
        return [
            GapClass::Strength->value => count($this->strengths),
            GapClass::Opportunity->value => count($this->opportunities),
            GapClass::CriticalGap->value => count($this->criticalGaps),
        ];
    }
}
