<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\Ability;
use App\Services\Identity\MfaSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConfirmMfaRequest extends FormRequest
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
        return ['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
