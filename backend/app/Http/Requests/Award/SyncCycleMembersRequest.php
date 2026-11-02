<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Data\Award\CycleMemberChanges;
use App\Enums\Award\CycleMemberRole;
use App\Support\ScalarValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SyncCycleMembersRequest extends FormRequest
{
    private const int MAX_MEMBERS = 50;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['reason' => ['nullable', 'string', 'min:10', 'max:1000']];

        foreach (CycleMemberRole::cases() as $role) {
            $rules[$role->value.'_ids'] = ['sometimes', 'present', 'array', 'max:'.self::MAX_MEMBERS];
            $rules[$role->value.'_ids.*'] = ['required', 'integer', 'min:1', 'distinct'];
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $keys = array_map(static fn (CycleMemberRole $role): string => $role->value.'_ids', CycleMemberRole::cases());

            if (! array_any($keys, fn (string $key): bool => $this->has($key))) {
                $validator->errors()->add($keys[0], __('validation.required', ['attribute' => $keys[0]]));
            }
        }];
    }

    public function changes(): CycleMemberChanges
    {
        $byRole = [];

        foreach (CycleMemberRole::cases() as $role) {
            $key = $role->value.'_ids';

            if ($this->has($key)) {
                $byRole[$role->value] = array_values(array_map(static fn (mixed $id): int => ScalarValue::int($id), (array) $this->input($key)));
            }
        }

        return new CycleMemberChanges($byRole);
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
