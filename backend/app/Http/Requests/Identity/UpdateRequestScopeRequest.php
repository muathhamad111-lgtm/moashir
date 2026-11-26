<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Settings\PathSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRequestScopeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'all_paths' => ['required', 'boolean'],
            'paths' => ['exclude_if:all_paths,true', 'required', 'array', 'min:1', 'max:'.count(AssessmentPath::reviewed())],
            'paths.*' => ['required', 'string', 'distinct', Rule::in(PathSetting::pathValues())],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<AssessmentPath>|null
     */
    public function paths(): ?array
    {
        if ($this->boolean('all_paths')) {
            return null;
        }

        $paths = [];

        foreach ((array) $this->input('paths', []) as $value) {
            $path = is_string($value) ? AssessmentPath::tryFrom($value) : null;

            if ($path instanceof AssessmentPath && $path->isReviewed()) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
