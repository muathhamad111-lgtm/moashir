<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Services\Notifications\NotificationTypeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateNotificationChannelsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audience' => ['sometimes', 'string', Rule::in(NotificationAudienceKind::values())],
            'channels' => ['required', 'array:in_app,email,sms'],
            'channels.in_app' => ['sometimes', 'boolean'],
            'channels.email' => ['sometimes', 'boolean'],
            'channels.sms' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = app(NotificationTypeCatalog::class)->of($this->code());
            if ($type->isCore) {
                $validator->errors()->add('channels', __('validation.custom.notification_channels.core'));
            }
            $audience = $this->audience();
            if ($audience instanceof NotificationAudienceKind && ! $type->reachesKind($audience)) {
                $validator->errors()->add('audience', __('validation.custom.notification_channels.audience'));
            }
        }];
    }

    public function audience(): ?NotificationAudienceKind
    {
        return NotificationAudienceKind::tryFrom($this->string('audience')->toString());
    }

    public function code(): NotificationCode
    {
        $code = $this->route('code');

        return $code instanceof NotificationCode ? $code : abort(404);
    }

    /**
     * @return array<string, bool> channel value => enabled
     */
    public function choices(): array
    {
        $choices = [];

        foreach (NotificationChannel::cases() as $channel) {
            if ($this->has('channels.'.$channel->value)) {
                $choices[$channel->value] = $this->boolean('channels.'.$channel->value);
            }
        }

        return $choices;
    }
}
