<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum CriteriaEditScope: string
{
    case All = 'all';
    case NewOnly = 'new_only';
    case ResetOpen = 'reset_open';

    public function rebindsOpenAssessments(): bool
    {
        return $this !== self::NewOnly;
    }

    public function keepsScoresOfUnchangedItems(): bool
    {
        return $this === self::All;
    }

    public function clearsAllOpenScores(): bool
    {
        return $this === self::ResetOpen;
    }

    public function notifiesAffectedEntitiesAndAssessors(): bool
    {
        return $this === self::ResetOpen;
    }

    public function isStricterThan(self $other): bool
    {
        return $this->strictness() > $other->strictness();
    }

    private function strictness(): int
    {
        return match ($this) {
            self::NewOnly => 1,
            self::All => 2,
            self::ResetOpen => 3,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
