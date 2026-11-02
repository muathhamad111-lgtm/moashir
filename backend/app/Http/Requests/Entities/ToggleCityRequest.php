<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use Illuminate\Foundation\Http\FormRequest;

final class ToggleCityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['active' => ['required', 'boolean']];
    }

    public function isActive(): bool
    {
        return $this->boolean('active');
    }
}
