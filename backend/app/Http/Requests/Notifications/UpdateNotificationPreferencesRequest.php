<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Data\Notifications\NotificationPreferenceChange;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Support\ScalarValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNotificationPreferencesRequest extends FormRequest
{
    private const int MAX_CHANGES = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_CHANGES],
            'preferences.*' => ['required', 'array:code,channel,enabled'],
            'preferences.*.code' => ['required', 'string', Rule::enum(NotificationCode::class)],
            'preferences.*.channel' => ['required', 'string', Rule::in(NotificationChannel::optionalValues())],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<NotificationPreferenceChange>
     */
    public function changes(): array
    {
        $changes = [];

        foreach ($this->array('preferences') as $preference) {
            $row = is_array($preference) ? $preference : [];
            $code = NotificationCode::from(ScalarValue::string($row['code'] ?? null));
            $channel = NotificationChannel::from(ScalarValue::string($row['channel'] ?? null));
            $changes[] = new NotificationPreferenceChange($code, $channel, filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN));
        }

        return $changes;
    }
}
