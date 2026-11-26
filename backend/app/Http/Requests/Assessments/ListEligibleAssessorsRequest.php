<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Identity\RequestPathScope;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ListEligibleAssessorsRequest extends FormRequest
{
    private ?Assessment $resolved = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assessment_id' => ['nullable', 'integer', 'min:1'],
            'path' => ['nullable', 'string', Rule::in(array_map(static fn (AssessmentPath $path): string => $path->value, $this->followedPaths()))],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('assessment_id') && ! $this->assessment() instanceof Assessment) {
                $validator->errors()->add('assessment_id', __('validation.exists', ['attribute' => 'assessment_id']));
            }
        }];
    }

    public function assessment(): ?Assessment
    {
        if ($this->resolved instanceof Assessment || ! $this->filled('assessment_id')) {
            return $this->resolved;
        }

        $assessment = Assessment::query()->find($this->integer('assessment_id'));
        $user = $this->user();

        if ($assessment instanceof Assessment && $user instanceof User && $user->can('viewRequest', $assessment)) {
            $this->resolved = $assessment;
        }

        return $this->resolved;
    }

    public function assessmentPath(): AssessmentPath
    {
        $assessment = $this->assessment();

        if ($assessment instanceof Assessment) {
            return $assessment->path;
        }

        return AssessmentPath::tryFrom($this->string('path')->toString()) ?? $this->followedPaths()[0];
    }

    public function search(): ?SearchTerm
    {
        return SearchTerm::tryFrom($this->string('search')->toString());
    }

    /**
     * @return non-empty-list<AssessmentPath>
     */
    private function followedPaths(): array
    {
        $user = $this->user();
        $paths = $user instanceof User ? app(RequestPathScope::class)->pathsFor($user) : [];

        return $paths === [] ? [AssessmentPath::Accredited] : $paths;
    }
}
