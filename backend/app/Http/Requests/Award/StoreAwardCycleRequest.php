<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

final class StoreAwardCycleRequest extends CycleAttributesRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->attributeRules('required'),
            'submission_end' => ['required', 'date', 'after:submission_start'],
            'committee_start' => ['required', 'date', 'after_or_equal:submission_end'],
            'committee_end' => ['required', 'date', 'after:committee_start'],
            'announcement_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:committee_end'],
        ];
    }
}
