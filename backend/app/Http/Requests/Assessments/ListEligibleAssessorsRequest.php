<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Models\Assessments\Assessment;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;

final class ListEligibleAssessorsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assessment_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function assessment(): ?Assessment
    {
        return $this->filled('assessment_id') ? Assessment::query()->find($this->integer('assessment_id')) : null;
    }

    public function search(): ?SearchTerm
    {
        return SearchTerm::tryFrom($this->string('search')->toString());
    }
}
