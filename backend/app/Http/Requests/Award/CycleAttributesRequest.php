<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\CycleAttributes;
use App\Enums\Criteria\ScaleKind;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Percent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CycleAttributesRequest extends FormRequest
{
    private const string SCORE_FORMAT = 'regex:/^\d{1,3}(\.\d{1,2})?$/';

    /**
     * @return array<string, mixed>
     */
    protected function attributeRules(string $presence): array
    {
        return [
            'name' => [$presence, 'array:ar,en'],
            'name.ar' => ['required_with:name', 'string', 'max:200'],
            'name.en' => ['required_with:name', 'string', 'max:200'],
            'year' => ['sometimes', 'nullable', 'integer', 'between:2000,2200'],
            'submission_start' => [$presence, 'date'],
            'submission_end' => [$presence, 'date'],
            'committee_start' => [$presence, 'date'],
            'committee_end' => [$presence, 'date'],
            'announcement_at' => ['sometimes', 'nullable', 'date'],
            'region_ids' => [$presence, 'array', 'min:1', 'max:13'],
            'region_ids.*' => ['required', 'integer', 'distinct', Rule::exists('regions', 'id')->where('is_active', true)],
            'criteria_version_id' => [$presence, 'integer', 'min:1'],
            'scale_kind' => ['sometimes', 'nullable', Rule::enum(ScaleKind::class)],
            'min_entity_age_years' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'disqualification_score' => ['sometimes', 'nullable', 'numeric', 'between:0,100', self::SCORE_FORMAT],
            'initial_review_sla_days' => ['sometimes', 'nullable', 'integer', 'between:1,120'],
            'participation_certificate_enabled' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function cycleAttributes(): CycleAttributes
    {
        return new CycleAttributes(
            $this->has('name') ? LocalizedText::fromArray((array) $this->input('name')) : null,
            $this->filled('year') ? $this->integer('year') : null,
            $this->dates(),
            $this->has('region_ids') ? array_values(array_map(static fn (mixed $id): int => ScalarValue::int($id), (array) $this->input('region_ids'))) : null,
            $this->filled('criteria_version_id') ? $this->integer('criteria_version_id') : null,
            $this->filled('scale_kind') ? ScaleKind::from($this->string('scale_kind')->toString()) : null,
            $this->filled('min_entity_age_years') ? $this->integer('min_entity_age_years') : null,
            $this->filled('disqualification_score') ? Percent::of($this->string('disqualification_score')->toString()) : null,
            $this->filled('initial_review_sla_days') ? $this->integer('initial_review_sla_days') : null,
            $this->has('participation_certificate_enabled') ? $this->boolean('participation_certificate_enabled') : null,
        );
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    /**
     * @return array<string, CarbonImmutable|null>
     */
    private function dates(): array
    {
        $dates = [];

        foreach (CycleAttributes::DATE_FIELDS as $field) {
            if ($this->has($field)) {
                $dates[$field] = $this->filled($field) ? CarbonImmutable::parse($this->string($field)->toString())->utc() : null;
            }
        }

        return $dates;
    }
}
