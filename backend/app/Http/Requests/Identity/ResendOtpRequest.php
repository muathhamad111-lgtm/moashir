<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\OtpChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ResendOtpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'uuid'],
            'channel' => ['nullable', Rule::enum(OtpChannel::class)],
        ];
    }

    public function challengeId(): string
    {
        return $this->string('challenge_id')->toString();
    }

    public function channel(): ?OtpChannel
    {
        return $this->enum('channel', OtpChannel::class);
    }
}
