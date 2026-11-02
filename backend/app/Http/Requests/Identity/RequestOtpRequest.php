<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Enums\Identity\OtpChannel;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RequestOtpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            'channel' => ['nullable', Rule::enum(OtpChannel::class)],
        ];
    }

    public function email(): EmailAddress
    {
        return new EmailAddress($this->string('email')->toString());
    }

    public function channel(): OtpChannel
    {
        return $this->enum('channel', OtpChannel::class) ?? OtpChannel::Email;
    }
}
