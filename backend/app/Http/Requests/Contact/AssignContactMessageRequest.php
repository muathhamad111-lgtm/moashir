<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

final class AssignContactMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignee_id' => ['present', 'nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function assigneeId(): ?int
    {
        return $this->filled('assignee_id') ? $this->integer('assignee_id') : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
