<?php

declare(strict_types=1);

namespace App\Http\Requests\Nda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One page of agreement versions or acceptances.
 */
final class PageNdaRequest extends FormRequest
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
