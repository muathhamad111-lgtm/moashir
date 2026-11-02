<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Enums\Integrations\IntegrationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterPaymentWebhookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(IntegrationMode::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function mode(): IntegrationMode
    {
        return IntegrationMode::from($this->string('mode')->toString());
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
