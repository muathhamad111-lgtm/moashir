<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Notifications\NotificationChannelChoice;
use App\Models\Identity\User;
use App\Models\Notifications\NotificationChannelSetting;

/**
 * Stores one channel choice only when it differs from how the type ships; choosing the shipped state again
 * removes the row, so the type goes back to its default. Callers run it inside their transaction.
 */
final readonly class NotificationChannelChoiceWriter
{
    public function write(User $editor, NotificationChannelChoice $choice): void
    {
        $row = NotificationChannelSetting::query()
            ->where('code', $choice->type->code)
            ->where('channel', $choice->channel)
            ->where('audience', $choice->audience)
            ->lockForUpdate()
            ->first();

        if ($choice->enabled === in_array($choice->channel, $choice->type->channels, true)) {
            $row?->delete();

            return;
        }

        $row ??= new NotificationChannelSetting(['code' => $choice->type->code, 'channel' => $choice->channel, 'audience' => $choice->audience]);
        $row->fill(['is_enabled' => $choice->enabled, 'updated_by_user_id' => $editor->id])->save();
    }
}
