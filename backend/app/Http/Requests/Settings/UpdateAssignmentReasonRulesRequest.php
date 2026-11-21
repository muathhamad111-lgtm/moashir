<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Assessments\AssignmentReasonRules;
use App\Data\Assessments\AssignmentReasonRulesUpdate;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAssignmentReasonRulesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assign_required' => ['required', 'boolean'],
            'reassign_required' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function rulesUpdate(): AssignmentReasonRulesUpdate
    {
        return new AssignmentReasonRulesUpdate(
            new AssignmentReasonRules($this->boolean('assign_required'), $this->boolean('reassign_required')),
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
