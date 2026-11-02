<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Support\StringList;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateRolePermissionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permission_keys' => ['present', 'array', 'max:200'],
            'permission_keys.*' => ['required', 'string', 'distinct', 'max:80'],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return StringList::from($this->input('permission_keys', []));
    }
}
