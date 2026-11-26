<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

final class ToggleCmsContentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }
}
