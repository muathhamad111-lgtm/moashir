<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Enums\Integrations\IntegrationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TestPaymentConnectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['mode' => ['sometimes', Rule::enum(IntegrationMode::class)]];
    }

    public function mode(): ?IntegrationMode
    {
        return $this->has('mode') ? IntegrationMode::from($this->string('mode')->toString()) : null;
    }
}
