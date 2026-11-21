<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

final class IndexLegalVersionsRequest extends FormRequest
{
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
        return $this->integer('per_page', 15);
    }
}
