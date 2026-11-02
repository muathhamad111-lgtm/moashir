<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Rules\UniqueUserEmail;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

final class ChangeOwnEmailRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['new_email' => ['required', 'string', 'email:filter', 'max:254', new UniqueUserEmail]];
    }

    public function newEmail(): EmailAddress
    {
        return new EmailAddress($this->string('new_email')->toString());
    }
}
