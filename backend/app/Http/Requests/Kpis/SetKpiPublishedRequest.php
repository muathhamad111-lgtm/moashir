<?php

declare(strict_types=1);

namespace App\Http\Requests\Kpis;

use Illuminate\Foundation\Http\FormRequest;

final class SetKpiPublishedRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'published' => ['required', 'boolean'],
        ];
    }

    public function published(): bool
    {
        return $this->boolean('published');
    }
}
