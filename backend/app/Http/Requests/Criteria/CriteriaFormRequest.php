<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\AttachmentRequirementData;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;
use Illuminate\Foundation\Http\FormRequest;

abstract class CriteriaFormRequest extends FormRequest
{
    protected const string MAX_WEIGHT = '100000';

    protected const int NAME_MAX = 200;

    protected const int DESCRIPTION_MAX = 2000;

    protected const int ATTACHMENTS_MAX = 20;

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
     * @return array<string, list<string>>
     */
    protected function attachmentRules(): array
    {
        return [
            'attachments' => ['sometimes', 'array', 'max:'.self::ATTACHMENTS_MAX],
            'attachments.*' => ['array:key,name,required'],
            'attachments.*.key' => ['nullable', 'string', 'regex:/^[a-z0-9_]{1,40}$/', 'distinct'],
            'attachments.*.name' => ['required', 'array:ar,en'],
            'attachments.*.name.ar' => ['required', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'attachments.*.name.en' => ['required', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'attachments.*.required' => ['required', 'boolean'],
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

        return array_values(array_map(static fn (mixed $item): AttachmentRequirementData => new AttachmentRequirementData(
            is_array($item) && is_string($item['key'] ?? null) ? $item['key'] : null,
            LocalizedText::fromArray(is_array($item) && is_array($item['name'] ?? null) ? $item['name'] : []),
            is_array($item) && filter_var($item['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ), $items));
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
