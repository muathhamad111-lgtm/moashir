<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class IndexBankAccountsRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 50;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:active'],
            'filter.active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function activeFilter(): ?bool
    {
        return $this->has('filter.active') && $this->input('filter.active') !== null ? $this->boolean('filter.active') : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
