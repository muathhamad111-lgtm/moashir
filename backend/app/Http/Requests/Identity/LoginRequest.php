<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Rules\RecognisedSignInIdentifier;
use App\Support\ValueObjects\SignInIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

final class LoginRequest extends FormRequest
{
    public const string LEGACY_EMAIL_FIELD = 'email';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:254', new RecognisedSignInIdentifier],
            'password' => ['required', 'string', 'max:128'],
        ];
    }

    public function identifier(): SignInIdentifier
    {
        return SignInIdentifier::tryParse($this->string('identifier')->toString())
            ?? throw new LogicException('The identifier is validated before it is read.');
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('identifier') && $this->has(self::LEGACY_EMAIL_FIELD)) {
            $this->merge(['identifier' => $this->input(self::LEGACY_EMAIL_FIELD)]);
        }
    }
}
