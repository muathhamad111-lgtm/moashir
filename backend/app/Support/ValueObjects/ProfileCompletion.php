<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Entities\ProfileSection;
use App\Enums\Entities\ProfileSectionField;
use App\Enums\Entities\ProfileSectionState;
use App\Support\Entities\ProfileFieldValues;
use InvalidArgumentException;

final readonly class ProfileCompletion
{
    private const int FULL = 100;

    /**
     * @param  list<ProfileSectionField>  $counted
     * @param  list<ProfileSectionField>  $missing
     */
    public function __construct(private array $counted, private array $missing)
    {
        foreach ($counted as $field) {
            if (! $field->isCounted()) {
                throw new InvalidArgumentException('An optional profile field is never counted: '.$field->value.'.');
            }
        }

        foreach ($missing as $field) {
            if (! in_array($field, $counted, true)) {
                throw new InvalidArgumentException('Only a counted profile field can be missing: '.$field->value.'.');
            }
        }
    }

    public static function of(ProfileFieldValues $values): self
    {
        $counted = $values->countedFields();

        return new self($counted, array_values(array_filter(
            $counted,
            static fn (ProfileSectionField $field): bool => ! $values->isFilled($field),
        )));
    }

    public function counted(): int
    {
        return count($this->counted);
    }

    public function completedCount(): int
    {
        return $this->counted() - count($this->missingFields());
    }

    public function percent(): int
    {
        return intdiv(self::FULL * $this->completedCount(), $this->counted());
    }

    public function isComplete(): bool
    {
        return $this->completedCount() === $this->counted();
    }

    public function isSectionComplete(ProfileSection $section): bool
    {
        return $this->missingIn($section) === [];
    }

    /**
     * @return list<ProfileSectionField>
     */
    public function missingFields(): array
    {
        return array_values(array_filter($this->counted, fn (ProfileSectionField $field): bool => in_array($field, $this->missing, true)));
    }

    /**
     * @return list<ProfileSectionField>
     */
    public function missingIn(ProfileSection $section): array
    {
        return array_values(array_filter($section->fields(), fn (ProfileSectionField $field): bool => in_array($field, $this->missing, true)));
    }

    /**
     * @return array{percent: int, counted: int, completed_count: int, sections: list<array{key: string, state: string, missing_count: int, missing_fields: list<string>}>}
     */
    public function toArray(): array
    {
        return [
            'percent' => $this->percent(),
            'counted' => $this->counted(),
            'completed_count' => $this->completedCount(),
            'sections' => array_map($this->sectionSummary(...), ProfileSection::cases()),
        ];
    }

    /**
     * @return array{key: string, state: string, missing_count: int, missing_fields: list<string>}
     */
    private function sectionSummary(ProfileSection $section): array
    {
        $missing = array_map(static fn (ProfileSectionField $field): string => $field->value, $this->missingIn($section));

        return [
            'key' => $section->value,
            'state' => ProfileSectionState::withMissing(count($missing))->value,
            'missing_count' => count($missing),
            'missing_fields' => $missing,
        ];
    }
}
