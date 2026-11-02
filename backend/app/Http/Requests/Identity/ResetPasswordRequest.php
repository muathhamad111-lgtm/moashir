<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Models\Identity\User;
use App\Services\Identity\PasswordPolicy;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(PasswordPolicy $policy): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            'password' => $policy->rules($this->targetUser()),
        ];
    }

    public function email(): EmailAddress
    {
        return new EmailAddress($this->string('email')->toString());
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }

    private function targetUser(): ?User
    {
        $email = $this->string('email')->toString();

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false
            ? null
            : User::query()->withEmail(new EmailAddress($email))->first();
    }
}
