<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Settings\AuthSettingsUpdate;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAuthSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity_second_factor_enabled' => ['sometimes', 'required', 'boolean'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): AuthSettingsUpdate
    {
        return new AuthSettingsUpdate(
            $this->has('entity_second_factor_enabled') ? $this->boolean('entity_second_factor_enabled') : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }
}
