<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\IdentityChangeListFilters;
use App\Enums\Identity\IdentityChangeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexIdentityChangesRequest extends FormRequest
{
    private const string ALL = 'all';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', Rule::in([...IdentityChangeStatus::values(), self::ALL])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): IdentityChangeListFilters
    {
        $status = $this->input('filter.status');

        return new IdentityChangeListFilters(
            is_string($status) ? IdentityChangeStatus::tryFrom($status) : IdentityChangeStatus::Pending,
            $this->integer('per_page', 15),
        );
    }
}
