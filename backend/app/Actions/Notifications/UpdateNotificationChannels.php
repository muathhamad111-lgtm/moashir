<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Models\Notifications\NotificationChannelSetting;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\SystemNotificationChannels;
use Illuminate\Support\Facades\DB;

/**
 * Stores a channel choice only when it differs from how the type ships; choosing the shipped state again
 * removes the row, so the type goes back to its default.
 */
final readonly class UpdateNotificationChannels
{
    public function __construct(private NotificationTypeCatalog $catalog, private SystemNotificationChannels $channels) {}

    /**
     * @param  array<string, bool>  $choices  channel value => enabled
     */
    public function handle(User $editor, NotificationCode $code, array $choices): void
    {
        $shipped = $this->catalog->of($code)->channels;

        DB::transaction(static function () use ($editor, $code, $choices, $shipped): void {
            foreach ($choices as $value => $enabled) {
                $channel = NotificationChannel::from($value);
                $row = NotificationChannelSetting::query()->where('code', $code)->where('channel', $channel)->lockForUpdate()->first();

                if ($enabled === in_array($channel, $shipped, true)) {
                    $row?->delete();

                    continue;
                }

                $row ??= new NotificationChannelSetting(['code' => $code, 'channel' => $channel]);
                $row->fill(['is_enabled' => $enabled, 'updated_by_user_id' => $editor->id])->save();
            }
        });

        $this->channels->forget();
    }
}
