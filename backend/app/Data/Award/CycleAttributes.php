<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Criteria\ScaleKind;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\Percent;
use Carbon\CarbonImmutable;

final readonly class CycleAttributes
{
    public const array DATE_FIELDS = ['submission_start', 'submission_end', 'committee_start', 'committee_end', 'announcement_at'];

    /**
     * @param  array<string, CarbonImmutable|null>  $dates
     * @param  list<int>|null  $regionIds
     */
    public function __construct(
        public ?LocalizedText $name = null,
        public ?int $year = null,
        public array $dates = [],
        public ?array $regionIds = null,
        public ?int $criteriaVersionId = null,
        public ?ScaleKind $scaleKind = null,
        public ?int $minEntityAgeYears = null,
        public ?Percent $disqualificationScore = null,
        public ?int $initialReviewSlaDays = null,
        public ?bool $participationCertificateEnabled = null,
        public ?Money $entryFee = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function columnValues(): array
    {
        return array_filter([
            'name' => $this->name,
            'year' => $this->year,
            'criteria_version_id' => $this->criteriaVersionId,
            'scale_kind' => $this->scaleKind,
            'min_entity_age_years' => $this->minEntityAgeYears,
            'disqualification_score' => $this->disqualificationScore,
            'initial_review_sla_days' => $this->initialReviewSlaDays,
            'participation_certificate_enabled' => $this->participationCertificateEnabled,
            'entry_fee' => $this->entryFee?->amount,
        ], static fn (mixed $value): bool => $value !== null) + $this->dates;
    }

    /**
     * @return list<string>
     */
    public function touchedFields(): array
    {
        $fields = array_map(static fn (int|string $field): string => (string) $field, array_keys($this->columnValues()));

        if ($this->regionIds !== null) {
            $fields[] = 'region_ids';
        }

        return $fields;
    }

    public function isEmpty(): bool
    {
        return $this->touchedFields() === [];
    }
}
