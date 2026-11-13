<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateBrandingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'accent_token' => ['required', 'string', 'max:30'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function token(): string
    {
        return $this->string('accent_token')->trim()->toString();
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->toString() : null;
    }
}
