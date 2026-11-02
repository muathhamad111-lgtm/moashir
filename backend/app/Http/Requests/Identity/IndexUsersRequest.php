<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Actions\Identity\ListStaffUsers;
use App\Data\Identity\UserListFilters;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexUsersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', 'array:role,status'],
            'filter.role' => ['nullable', Rule::enum(RoleKey::class)],
            'filter.status' => ['nullable', Rule::enum(UserStatus::class)],
            'sort' => ['nullable', Rule::in(ListStaffUsers::SORTABLE)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): UserListFilters
    {
        $role = $this->input('filter.role');
        $status = $this->input('filter.status');

        return new UserListFilters(
            SearchTerm::tryFrom($this->string('search')->toString()),
            is_string($role) ? RoleKey::tryFrom($role) : null,
            is_string($status) ? UserStatus::tryFrom($status) : null,
            $this->filled('sort') ? $this->string('sort')->toString() : 'name',
            $this->integer('per_page', 15),
        );
    }
}
