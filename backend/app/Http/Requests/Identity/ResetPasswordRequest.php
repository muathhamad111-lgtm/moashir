<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Services\Identity\PasswordPolicy;
use App\Services\Identity\UsablePasswordResetLinkFinder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The account comes from the link token alone; an `email` an older client still sends is ignored.
 */
final class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(PasswordPolicy $policy, UsablePasswordResetLinkFinder $links): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
            'password' => $policy->rules($links->tryFind($this->token())?->user),
        ];
    }

    public function token(): string
    {
        return $this->string('token')->toString();
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
