<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\PermissionModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPermissionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:module'],
            'filter.module' => ['nullable', Rule::enum(PermissionModule::class)],
        ];
    }

    public function module(): ?PermissionModule
    {
        $module = $this->input('filter.module');

        return is_string($module) ? PermissionModule::tryFrom($module) : null;
    }
}
