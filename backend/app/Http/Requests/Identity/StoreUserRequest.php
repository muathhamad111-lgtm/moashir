<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Data\Identity\StaffUserData;
use App\Enums\Identity\Gender;
use App\Enums\Identity\Locale;
use App\Rules\SaudiNationalId;
use App\Rules\UniqueUserEmail;
use App\Support\StringList;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\NationalId;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'string', 'email:filter', 'max:254', new UniqueUserEmail],
            'phone' => ['nullable', 'string', 'regex:'.PhoneNumber::SAUDI_MOBILE_PATTERN],
            'roles' => ['required', 'array', 'min:1', 'max:10'],
            'roles.*' => ['required', 'string', 'distinct', 'max:60'],
            'locale' => ['nullable', Rule::enum(Locale::class)],
            'gender' => ['required', Rule::enum(Gender::class)],
            'national_id' => ['nullable', 'string', new SaudiNationalId],
        ];
    }

    public function staffUser(): StaffUserData
    {
        return new StaffUserData(
            $this->string('name')->trim()->toString(),
            new EmailAddress($this->string('email')->toString()),
            $this->filled('phone') ? new PhoneNumber($this->string('phone')->toString()) : null,
            StringList::from($this->input('roles')),
            $this->enum('locale', Locale::class) ?? Locale::Arabic,
            $this->enum('gender', Gender::class) ?? throw new LogicException('Gender is validated as required.'),
            $this->filled('national_id') ? new NationalId($this->string('national_id')->toString()) : null,
        );
    }
}
