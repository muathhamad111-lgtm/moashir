<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Rules\UniqueUserEmail;
use App\Support\ValueObjects\EmailAddress;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class RequestEmailChangeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'new_email' => ['required', 'string', 'email:filter', 'max:254', $this->differentFromCurrent(...), new UniqueUserEmail],
        ];
    }

    public function newEmail(): EmailAddress
    {
        return new EmailAddress($this->string('new_email')->toString());
    }

    /**
     * @param  Closure(string): mixed  $fail
     */
    private function differentFromCurrent(string $attribute, mixed $value, Closure $fail): void
    {
        $current = $this->user()?->email;

        if (is_string($value) && is_string($current) && mb_strtolower(trim($value)) === mb_strtolower($current)) {
            $fail(__('validation.custom.new_email.same_as_current'));
        }
    }
}
