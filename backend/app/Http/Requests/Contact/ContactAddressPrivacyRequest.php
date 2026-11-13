<?php

declare(strict_types=1);

namespace App\Http\Requests\Contact;

use App\Data\Contact\ContactAddressRequest;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

final class ContactAddressPrivacyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function addressRequest(): ContactAddressRequest
    {
        return new ContactAddressRequest(new EmailAddress($this->string('email')->toString()), $this->string('reason')->trim()->toString());
    }
}
