<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum FeedbackKind: string
{
    case Strength = 'strength';
    case Improvement = 'improvement';
    case Note = 'note';

    public function field(): string
    {
        return match ($this) {
            self::Strength => 'strengths',
            self::Improvement => 'improvement_points',
            self::Note => 'notes',
        };
    }

    public static function fromField(string $field): ?self
    {
        foreach (self::cases() as $kind) {
            if ($kind->field() === $field) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return array_map(static fn (self $kind): string => $kind->field(), self::cases());
    }
}
