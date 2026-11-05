<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Data\Assessments\ReviewCompletion;
use Illuminate\Foundation\Http\FormRequest;

final class CompleteReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
            'issue_certificate' => ['sometimes', 'boolean'],
        ];
    }

    public function completion(): ReviewCompletion
    {
        return new ReviewCompletion(
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
            $this->boolean('issue_certificate', true),
        );
    }
}
