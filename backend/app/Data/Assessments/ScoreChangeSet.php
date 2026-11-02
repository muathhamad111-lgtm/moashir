<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class ScoreChangeSet
{
    /**
     * @param  list<ScoreChange>  $changes
     */
    public function __construct(public array $changes = []) {}

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public function count(): int
    {
        return count($this->changes);
    }

    /**
     * @return array<string, string|null>
     */
    public function before(): array
    {
        $values = [];

        foreach ($this->changes as $change) {
            $values[$change->code] = $change->before?->toDecimal();
        }

        return $values;
    }

    /**
     * @return array<string, string|null>
     */
    public function after(): array
    {
        $values = [];

        foreach ($this->changes as $change) {
            $values[$change->code] = $change->after?->toDecimal();
        }

        return $values;
    }
}
