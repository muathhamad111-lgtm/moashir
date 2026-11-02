<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Support\ValueObjects\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;

final class SendTestEmailRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['recipient' => ['required', 'email:rfc', 'max:255']];
    }

    public function recipient(): EmailAddress
    {
        return new EmailAddress($this->string('recipient')->toString());
    }
}
