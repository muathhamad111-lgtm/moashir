<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use Illuminate\Foundation\Http\FormRequest;

final class SecretaryNoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
