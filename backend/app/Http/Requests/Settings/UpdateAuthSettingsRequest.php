<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Settings\AuthSettingsUpdate;
use App\Enums\Identity\OtpChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateAuthSettingsRequest extends FormRequest
{
    private const string CHANNELS = 'entity_sign_in_code_channels';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            self::CHANNELS => ['sometimes', 'required', 'array', 'list', 'max:'.count(OtpChannel::cases())],
            self::CHANNELS.'.*' => ['required', 'string', 'distinct', Rule::enum(OtpChannel::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(SmsAvailability $sms): array
    {
        return [function (Validator $validator) use ($sms): void {
            if (! $validator->errors()->isEmpty() || ! $this->has(self::CHANNELS)) {
                return;
            }

            $message = $this->channelsProblem($this->channels(), $sms);

            if ($message !== null) {
                $validator->errors()->add(self::CHANNELS, __($message));
            }
        }];
    }

    public function toUpdate(): AuthSettingsUpdate
    {
        return new AuthSettingsUpdate(
            $this->has(self::CHANNELS) ? $this->channels() : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
        );
    }

    /**
     * @param  list<OtpChannel>  $channels
     */
    private function channelsProblem(array $channels, SmsAvailability $sms): ?string
    {
        return match (true) {
            ! in_array(OtpChannel::Email, $channels, true) => 'validation.custom.entity_sign_in_code_channels.email_required',
            in_array(OtpChannel::Sms, $channels, true) && ! $sms->isAvailable() => 'validation.custom.entity_sign_in_code_channels.sms_unavailable',
            default => null,
        };
    }

    /**
     * @return list<OtpChannel>
     */
    private function channels(): array
    {
        $submitted = $this->array(self::CHANNELS);

        return array_values(array_filter(
            OtpChannel::cases(),
            static fn (OtpChannel $channel): bool => in_array($channel->value, $submitted, true),
        ));
    }
}
