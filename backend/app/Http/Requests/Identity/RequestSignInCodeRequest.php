<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Rules\RecognisedSignInIdentifier;
use App\Support\ValueObjects\SignInIdentifier;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

final class RequestSignInCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:254', new RecognisedSignInIdentifier],
        ];
    }

    public function identifier(): SignInIdentifier
    {
        return SignInIdentifier::tryParse($this->string('identifier')->toString())
            ?? throw new LogicException('The identifier is validated before it is read.');
    }
}
