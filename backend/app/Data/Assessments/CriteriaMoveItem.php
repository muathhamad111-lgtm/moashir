<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ScoreLevel;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;

final readonly class CriteriaMoveItem
{
    public function __construct(public ScoreLevel $level, public string $code, public ?LocalizedText $label) {}

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, LocalizedText>  $labels
     */
    public static function from(array $stored, array $labels): self
    {
        $code = ScalarValue::string($stored['code'] ?? null);

        return new self(ScoreLevel::from(ScalarValue::string($stored['level'] ?? null)), $code, $labels[$code] ?? null);
    }

    /**
     * @return array{level: string, code: string, label: array{ar: string, en: string}|null}
     */
    public function toArray(): array
    {
        return ['level' => $this->level->value, 'code' => $this->code, 'label' => $this->label?->toArray()];
    }
}
