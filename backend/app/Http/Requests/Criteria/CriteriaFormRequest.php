<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\AttachmentRequirementData;
use App\Enums\Criteria\ScoringType;
use App\Enums\Files\EvidenceFileType;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

abstract class CriteriaFormRequest extends FormRequest
{
    protected const string MAX_WEIGHT = '100000';

    protected const int NAME_MAX = 200;

    protected const int DESCRIPTION_MAX = 2000;

    protected const int ATTACHMENTS_MAX = 20;

    protected const int GUIDANCE_MAX = 500;

    protected const int MAX_FILES = 50;

    /**
     * @return array<string, list<string>>
     */
    protected function localizedTextRules(string $field, string $presence, int $max): array
    {
        return [
            $field => [...$this->presence($presence), 'array:ar,en'],
            "{$field}.ar" => ["required_with:{$field}", 'string', 'min:2', "max:{$max}"],
            "{$field}.en" => ["required_with:{$field}", 'string', 'min:2', "max:{$max}"],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function weightRules(string $presence): array
    {
        return ['weight' => [...$this->presence($presence), 'numeric', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_WEIGHT]];
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    protected function scoringTypeRules(string ...$fields): array
    {
        return array_fill_keys($fields, ['sometimes', Rule::enum(ScoringType::class)]);
    }

    protected function scoringType(string $field): ?ScoringType
    {
        return $this->enum($field, ScoringType::class);
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    protected function attachmentRules(): array
    {
        return [
            'attachments' => ['sometimes', 'array', 'max:'.self::ATTACHMENTS_MAX],
            'attachments.*' => ['array:key,name,required,guidance,allowed_types,max_files'],
            'attachments.*.key' => ['nullable', 'string', 'regex:/^[a-z0-9_]{1,40}$/', 'distinct'],
            'attachments.*.name' => ['required', 'array:ar,en'],
            'attachments.*.name.ar' => ['required', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'attachments.*.name.en' => ['required', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'attachments.*.required' => ['required', 'boolean'],
            'attachments.*.guidance' => ['nullable', 'array:ar,en'],
            'attachments.*.guidance.ar' => ['required_with:attachments.*.guidance', 'string', 'min:2', 'max:'.self::GUIDANCE_MAX],
            'attachments.*.guidance.en' => ['required_with:attachments.*.guidance', 'string', 'min:2', 'max:'.self::GUIDANCE_MAX],
            'attachments.*.allowed_types' => ['nullable', 'array', 'min:1', 'max:'.count(EvidenceFileType::cases())],
            'attachments.*.allowed_types.*' => ['required', 'string', 'distinct', Rule::enum(EvidenceFileType::class)],
            'attachments.*.max_files' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_FILES],
        ];
    }

    protected function localizedText(string $field): ?LocalizedText
    {
        $value = $this->input($field);

        return is_array($value) ? LocalizedText::fromArray($value) : null;
    }

    protected function weight(): ?Weight
    {
        $value = $this->input('weight');

        return is_string($value) || is_int($value) || is_float($value) ? Weight::of($value) : null;
    }

    /**
     * @return list<AttachmentRequirementData>|null
     */
    protected function attachments(): ?array
    {
        $items = $this->input('attachments');

        if (! is_array($items)) {
            return null;
        }

        return array_values(array_map(fn (mixed $item): AttachmentRequirementData => $this->attachmentOf(is_array($item) ? $item : []), $items));
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private function attachmentOf(array $item): AttachmentRequirementData
    {
        $types = is_array($item['allowed_types'] ?? null) ? $item['allowed_types'] : null;

        return new AttachmentRequirementData(
            is_string($item['key'] ?? null) ? $item['key'] : null,
            LocalizedText::fromArray(is_array($item['name'] ?? null) ? $item['name'] : []),
            filter_var($item['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            is_array($item['guidance'] ?? null) ? LocalizedText::fromArray($item['guidance']) : null,
            $types === null ? null : array_values(array_map(static fn (mixed $type): EvidenceFileType => EvidenceFileType::from(ScalarValue::string($type)), $types)),
            is_numeric($item['max_files'] ?? null) ? (int) $item['max_files'] : null,
        );
    }

    /**
     * @return list<string>
     */
    private function presence(string $presence): array
    {
        return match ($presence) {
            'required' => ['required'],
            'sometimes' => ['sometimes', 'required'],
            default => ['nullable'],
        };
    }
}
