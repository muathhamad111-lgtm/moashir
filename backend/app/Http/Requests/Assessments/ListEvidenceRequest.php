<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

final class ListEvidenceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:sub_criterion_id'],
            'filter.sub_criterion_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function subId(): ?int
    {
        return $this->filled('filter.sub_criterion_id') ? $this->integer('filter.sub_criterion_id') : null;
    }
}
