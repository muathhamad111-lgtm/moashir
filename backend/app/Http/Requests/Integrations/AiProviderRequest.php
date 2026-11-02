<?php

declare(strict_types=1);

namespace App\Http\Requests\Integrations;

use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AiProviderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['provider' => [$this->isMethod('GET') ? 'required' : 'sometimes', 'nullable', Rule::in(IntegrationProvider::valuesOf(IntegrationKind::Ai))]];
    }

    public function provider(): ?IntegrationProvider
    {
        return IntegrationProvider::tryFrom($this->string('provider')->toString());
    }
}
