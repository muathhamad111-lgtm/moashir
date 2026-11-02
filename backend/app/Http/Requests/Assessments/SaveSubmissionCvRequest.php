<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

final class SaveSubmissionCvRequest extends FormRequest
{
    private const int MAX_ITEMS = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cv' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'cv.*' => ['nullable', 'string', 'max:'.config()->integer('moashir.assessment.cv_max_chars')],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<array-key, string|null>
     */
    public function bodies(): array
    {
        $bodies = [];

        foreach ((array) $this->validated('cv') as $subId => $body) {
            $bodies[$subId] = is_string($body) ? $body : null;
        }

        return $bodies;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
