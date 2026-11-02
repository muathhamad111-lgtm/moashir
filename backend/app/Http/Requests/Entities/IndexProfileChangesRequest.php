<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use Illuminate\Foundation\Http\FormRequest;

final class IndexProfileChangesRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
