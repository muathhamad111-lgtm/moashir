<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendTestSmsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'regex:'.PhoneNumber::SAUDI_MOBILE_PATTERN],
            'provider' => ['sometimes', 'nullable', Rule::in(IntegrationProvider::valuesOf(IntegrationKind::Sms))],
        ];
    }

    public function recipient(): PhoneNumber
    {
        return new PhoneNumber($this->string('recipient')->toString());
    }

    public function provider(): ?IntegrationProvider
    {
        return IntegrationProvider::tryFrom($this->string('provider')->toString());
    }
}
