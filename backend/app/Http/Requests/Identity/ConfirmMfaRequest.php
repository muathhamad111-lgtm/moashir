<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmMfaRequest extends FormRequest
{
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
