<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Support\ValueObjects\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

final class RegistrationEmailCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'official_email' => ['required', 'string', 'email:filter', 'max:254'],
        ];
    }

    public function officialEmail(): EmailAddress
    {
        return new EmailAddress($this->string('official_email')->toString());
    }
}
