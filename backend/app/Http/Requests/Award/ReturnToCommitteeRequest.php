<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use Illuminate\Foundation\Http\FormRequest;

final class ReturnToCommitteeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'general_note' => ['required', 'string', 'min:10', 'max:2000'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function generalNote(): string
    {
        return $this->string('general_note')->trim()->toString();
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
