<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Data\Notifications\NotificationChannelBulkChange;
use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Support\ScalarValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A bulk switch names its types by code, by module, or every type; its channels and audiences default to all.
 */
final class BulkUpdateNotificationChannelsRequest extends FormRequest
{
    private const int MAX_CODES = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'scope' => ['required', 'string', Rule::in(['codes', 'module', 'all'])],
            'codes' => ['required_if:scope,codes', 'array', 'max:'.self::MAX_CODES],
            'codes.*' => ['string', 'distinct', Rule::in(NotificationCode::values())],
            'module' => ['required_if:scope,module', 'string', Rule::in($this->modules())],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['string', 'distinct', Rule::in(array_map(static fn (NotificationChannel $channel): string => $channel->value, NotificationChannel::cases()))],
            'audiences' => ['sometimes', 'array', 'min:1'],
            'audiences.*' => ['string', 'distinct', Rule::in(NotificationAudienceKind::values())],
        ];
    }

    public function change(): NotificationChannelBulkChange
    {
        $valid = $this->validated();

        return new NotificationChannelBulkChange(
            $this->codes($valid),
            isset($valid['channels']) ? array_map(NotificationChannel::from(...), $this->strings($valid['channels'])) : NotificationChannel::cases(),
            isset($valid['audiences']) ? array_map(NotificationAudienceKind::from(...), $this->strings($valid['audiences'])) : NotificationAudienceKind::cases(),
            (bool) $valid['enabled'],
        );
    }

    /**
     * @param  array<array-key, mixed>  $valid
     * @return list<NotificationCode>
     */
    private function codes(array $valid): array
    {
        $module = ScalarValue::string($valid['module'] ?? null);

        return match ($valid['scope'] ?? null) {
            'codes' => array_map(NotificationCode::from(...), $this->strings($valid['codes'] ?? [])),
            'module' => array_values(array_filter(NotificationCode::cases(), static fn (NotificationCode $code): bool => $code->module() === $module)),
            default => NotificationCode::cases(),
        };
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_map(ScalarValue::string(...), $values)) : [];
    }

    /**
     * @return list<string>
     */
    private function modules(): array
    {
        return array_values(array_unique(array_map(static fn (NotificationCode $code): string => $code->module(), NotificationCode::cases())));
    }
}
