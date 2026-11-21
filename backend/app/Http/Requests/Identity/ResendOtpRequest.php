<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class ResendOtpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'uuid'],
        ];
    }

    public function challengeId(): string
    {
        return $this->string('challenge_id')->toString();
    }
}
