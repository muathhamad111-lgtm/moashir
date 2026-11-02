<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use Illuminate\Foundation\Http\FormRequest;

final class AppointCommitteeAssessorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function userId(): int
    {
        return $this->integer('user_id');
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
