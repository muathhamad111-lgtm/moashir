<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Models\Identity\User;
use App\Services\Identity\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(PasswordPolicy $policy): array
    {
        $user = $this->user();

        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $policy->rules($user instanceof User ? $user : null),
        ];
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
