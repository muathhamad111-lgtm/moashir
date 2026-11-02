<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\UserProfileChanges;
use App\Models\Identity\User;
use App\Rules\UniqueUserEmail;
use App\Support\StringList;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('managedUser');

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'email' => ['sometimes', 'string', 'email:filter', 'max:254', new UniqueUserEmail($target instanceof User ? $target->id : null)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.PhoneNumber::SAUDI_MOBILE_PATTERN],
            'roles' => ['sometimes', 'array', 'min:1', 'max:10'],
            'roles.*' => ['required', 'string', 'distinct', 'max:60'],
        ];
    }

    public function changes(): UserProfileChanges
    {
        return new UserProfileChanges(
            $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            $this->filled('email') ? new EmailAddress($this->string('email')->toString()) : null,
            $this->filled('phone') ? new PhoneNumber($this->string('phone')->toString()) : null,
            $this->has('roles') ? StringList::from($this->input('roles')) : null,
        );
    }
}
