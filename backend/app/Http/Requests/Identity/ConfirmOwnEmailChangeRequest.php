<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmOwnEmailChangeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'uuid'],
            'code' => ['required', 'string', 'regex:/^[0-9]{'.config()->integer('moashir.auth.otp.length').'}$/'],
        ];
    }

    public function challengeId(): string
    {
        return $this->string('challenge_id')->toString();
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
