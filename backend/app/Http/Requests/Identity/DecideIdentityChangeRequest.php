<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class DecideIdentityChangeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['note' => [$this->routeIs('identity-changes.reject') ? 'required' : 'nullable', 'string', 'min:10', 'max:1000']];
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }

    public function requiredNote(): string
    {
        return $this->string('note')->trim()->toString();
    }
}
