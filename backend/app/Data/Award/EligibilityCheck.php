<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\EligibilityRule;

final readonly class EligibilityCheck
{
    public function __construct(
        public EligibilityRule $rule,
        public bool $passed,
        public int|string|null $required,
        public int|string|null $actual,
    ) {}

    /**
     * @return array{rule: string, passed: bool, required: int|string|null, actual: int|string|null}
     */
    public function toArray(): array
    {
        return ['rule' => $this->rule->value, 'passed' => $this->passed, 'required' => $this->required, 'actual' => $this->actual];
    }
}
