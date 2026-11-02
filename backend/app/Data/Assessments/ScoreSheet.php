<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\RawScore;

final readonly class ScoreSheet
{
    /**
     * @param  array<string, ScoredUnit>  $entries
     */
    private function __construct(private array $entries) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param  iterable<ScoredUnit>  $scoredUnits
     */
    public static function of(iterable $scoredUnits): self
    {
        $entries = [];

        foreach ($scoredUnits as $scored) {
            $entries[$scored->unit->key()] = $scored;
        }

        return new self($entries);
    }

    /**
     * @param  list<string>  $clearedKeys
     */
    public function merge(self $changes, array $clearedKeys): self
    {
        $entries = $this->entries;

        foreach ($clearedKeys as $key) {
            unset($entries[$key]);
        }

        return new self([...$entries, ...$changes->entries]);
    }

    public function has(ScorableUnit $unit): bool
    {
        return isset($this->entries[$unit->key()]);
    }

    public function rawFor(ScorableUnit $unit): ?RawScore
    {
        return $this->rawForKey($unit->key());
    }

    public function rawForKey(string $key): ?RawScore
    {
        return ($this->entries[$key] ?? null)?->raw;
    }

    /**
     * @return list<ScoredUnit>
     */
    public function entries(): array
    {
        return array_values($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
