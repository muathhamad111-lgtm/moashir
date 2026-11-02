<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Data\Criteria\TextCorrection;
use App\Data\Criteria\TextCorrectionBatch;
use App\Enums\Criteria\CriteriaNodeType;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Validation\Rule;

final class CorrectCriteriaTextRequest extends CriteriaFormRequest
{
    private const array TEXT_FIELDS = ['name', 'description', 'assessor_note_label'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->localizedTextRules('label', 'nullable', self::NAME_MAX),
            'nodes' => ['required_without:label', 'array', 'max:200'],
            'nodes.*' => ['array:type,id,name,description,assessor_note_label'],
            'nodes.*.type' => ['required', Rule::enum(CriteriaNodeType::class)->except([CriteriaNodeType::Version])],
            'nodes.*.id' => ['required', 'integer', 'min:1'],
            'nodes.*.name' => ['sometimes', 'required', 'prohibited_if:nodes.*.type,point', 'array:ar,en'],
            'nodes.*.name.ar' => ['required_with:nodes.*.name', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'nodes.*.name.en' => ['required_with:nodes.*.name', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'nodes.*.description' => ['sometimes', 'prohibited_if:nodes.*.type,attachment', 'required_if:nodes.*.type,point', 'nullable', 'array:ar,en'],
            'nodes.*.description.ar' => ['required_with:nodes.*.description', 'string', 'min:2', 'max:'.self::DESCRIPTION_MAX],
            'nodes.*.description.en' => ['required_with:nodes.*.description', 'string', 'min:2', 'max:'.self::DESCRIPTION_MAX],
            'nodes.*.assessor_note_label' => ['sometimes', 'prohibited_unless:nodes.*.type,sub', 'nullable', 'array:ar,en'],
            'nodes.*.assessor_note_label.ar' => ['required_with:nodes.*.assessor_note_label', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'nodes.*.assessor_note_label.en' => ['required_with:nodes.*.assessor_note_label', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function batch(): TextCorrectionBatch
    {
        $nodes = $this->input('nodes');

        return new TextCorrectionBatch(
            $this->localizedText('label'),
            is_array($nodes) ? array_values(array_map($this->correction(...), array_filter($nodes, is_array(...)))) : [],
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function correction(array $node): TextCorrection
    {
        $fields = [];

        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $node)) {
                $fields[$field] = is_array($node[$field]) ? LocalizedText::fromArray($node[$field]) : null;
            }
        }

        return new TextCorrection(
            CriteriaNodeType::from(is_string($node['type'] ?? null) ? $node['type'] : ''),
            is_numeric($node['id'] ?? null) ? (int) $node['id'] : 0,
            $fields,
        );
    }
}
