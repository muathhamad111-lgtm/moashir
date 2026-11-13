<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\AssessorQualification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAssessorQualificationsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qualifications' => ['present', 'array', 'max:'.count(AssessorQualification::cases())],
            'qualifications.*' => ['required', 'string', 'distinct', Rule::enum(AssessorQualification::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<AssessorQualification>
     */
    public function qualifications(): array
    {
        $qualifications = [];

        foreach ((array) $this->input('qualifications', []) as $value) {
            $qualification = is_string($value) ? AssessorQualification::tryFrom($value) : null;

            if ($qualification instanceof AssessorQualification) {
                $qualifications[] = $qualification;
            }
        }

        return $qualifications;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
