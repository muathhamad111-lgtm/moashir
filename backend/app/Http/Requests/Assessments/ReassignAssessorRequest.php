<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessments;

use Illuminate\Foundation\Http\FormRequest;

final class ReassignAssessorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assessor_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function assessorId(): int
    {
        return $this->integer('assessor_id');
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
