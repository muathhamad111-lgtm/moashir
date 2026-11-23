<?php

declare(strict_types=1);

namespace App\Http\Requests\Nda;

use App\Data\Nda\NdaSettingsUpdate;
use App\Enums\Identity\RoleKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNdaSettingsRequest extends FormRequest
{
    public const int MAX_REMINDER_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required_without:reminder_days', 'array'],
            'roles.*' => ['string', 'distinct', Rule::in(RoleKey::staffValues())],
            'reminder_days' => ['required_without:roles', 'integer', 'min:1', 'max:'.self::MAX_REMINDER_DAYS],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'roles' => __('nda.attributes.roles'),
            'reminder_days' => __('nda.attributes.reminder_days'),
            'reason' => __('nda.attributes.reason'),
        ];
    }

    public function update(): NdaSettingsUpdate
    {
        $roles = $this->validated('roles');

        return new NdaSettingsUpdate(
            is_array($roles) ? array_values(array_filter(RoleKey::cases(), static fn (RoleKey $role): bool => in_array($role->value, $roles, true))) : null,
            $this->filled('reminder_days') ? $this->integer('reminder_days') : null,
            $this->string('reason')->trim()->toString(),
        );
    }
}
