<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\Ability;
use App\Services\Identity\MfaSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class StartMfaEnrollmentRequest extends FormRequest
{
    public function authorize(MfaSubject $subject): bool
    {
        return Gate::forUser($subject->resolve())->allows(Ability::EnrollMfa->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => [Rule::requiredIf(fn (): bool => $this->user('web') !== null), 'string', 'current_password:web'],
        ];
    }
}
