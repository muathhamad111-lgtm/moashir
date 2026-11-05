<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartMfaEnrollmentRequest extends FormRequest
{
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
