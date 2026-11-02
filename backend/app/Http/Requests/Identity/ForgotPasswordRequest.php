<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Support\ValueObjects\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:filter', 'max:254']];
    }

    public function email(): EmailAddress
    {
        return new EmailAddress($this->string('email')->toString());
    }
}
