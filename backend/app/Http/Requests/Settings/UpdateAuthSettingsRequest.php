<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Settings\AuthSettingsUpdate;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\RoleKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateAuthSettingsRequest extends FormRequest
{
    private const string CHANNELS = 'entity_sign_in_code_channels';

    private const string GENERAL_EMAIL = 'general_email_notifications';

    private const string TWO_FACTOR = 'two_factor_enabled';

    private const string TWO_FACTOR_ROLES = 'two_factor_roles';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            self::CHANNELS => ['sometimes', 'required', 'array', 'list', 'max:'.count(OtpChannel::cases())],
            self::CHANNELS.'.*' => ['required', 'string', 'distinct', Rule::enum(OtpChannel::class)],
            self::GENERAL_EMAIL => ['sometimes', 'required', 'boolean'],
            self::TWO_FACTOR => ['sometimes', 'required', 'boolean'],
            self::TWO_FACTOR_ROLES => ['sometimes', 'present', 'array', 'list', 'max:'.count(RoleKey::cases())],
            self::TWO_FACTOR_ROLES.'.*' => ['required', 'string', 'distinct', Rule::enum(RoleKey::class)],
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
            $this->has(self::GENERAL_EMAIL) ? $this->boolean(self::GENERAL_EMAIL) : null,
            $this->has(self::TWO_FACTOR) ? $this->boolean(self::TWO_FACTOR) : null,
            $this->has(self::TWO_FACTOR_ROLES) ? $this->twoFactorRoles() : null,
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
     * @return list<RoleKey>
     */
    private function twoFactorRoles(): array
    {
        $submitted = $this->array(self::TWO_FACTOR_ROLES);

        return array_values(array_filter(RoleKey::cases(), static fn (RoleKey $role): bool => in_array($role->value, $submitted, true)));
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
