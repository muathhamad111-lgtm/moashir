<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\FeedbackKind;

final readonly class FeedbackSheet
{
    /**
     * @param  array<string, array<string, string>>  $bodies
     */
    public function __construct(private array $bodies) {}

    public function bodyOf(string $subCode, FeedbackKind $kind): ?string
    {
        return $this->bodies[$subCode][$kind->value] ?? null;
    }

    /**
     * @return array{strengths: string|null, improvement_points: string|null, notes: string|null}
     */
    public function fieldsOf(string $subCode): array
    {
        return [
            'strengths' => $this->bodyOf($subCode, FeedbackKind::Strength),
            'improvement_points' => $this->bodyOf($subCode, FeedbackKind::Improvement),
            'notes' => $this->bodyOf($subCode, FeedbackKind::Note),
        ];
    }

    /**
     * @return list<string>
     */
    public function subCodes(): array
    {
        return array_keys($this->bodies);
    }
}
