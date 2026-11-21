<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\UserProfileChanges;
use App\Enums\Identity\Gender;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\User;
use App\Rules\SaudiNationalId;
use App\Rules\UniqueUserEmail;
use App\Support\StringList;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\NationalId;
use App\Support\ValueObjects\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('managedUser');

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:150', $this->mustKeepNameOf($target)],
            'email' => ['sometimes', 'string', 'email:filter', 'max:254', new UniqueUserEmail($target instanceof User ? $target->id : null)],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:'.PhoneNumber::SAUDI_MOBILE_PATTERN],
            'roles' => ['sometimes', 'array', 'min:1', 'max:10'],
            'roles.*' => ['required', 'string', 'distinct', 'max:60'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
            'national_id' => ['sometimes', 'nullable', 'string', new SaudiNationalId],
        ];
    }

    private function mustKeepNameOf(mixed $target): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($target): void {
            if ($target instanceof User && $target->status !== UserStatus::Invited && is_string($value) && trim($value) !== $target->name) {
                $fail(__('validation.custom.name.requires_correction_request'));
            }
        };
    }

    public function changes(): UserProfileChanges
    {
        return new UserProfileChanges(
            $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            $this->filled('email') ? new EmailAddress($this->string('email')->toString()) : null,
            $this->filled('phone') ? new PhoneNumber($this->string('phone')->toString()) : null,
            $this->has('roles') ? StringList::from($this->input('roles')) : null,
            $this->enum('gender', Gender::class),
            $this->filled('national_id') ? new NationalId($this->string('national_id')->toString()) : null,
        );
    }
}
