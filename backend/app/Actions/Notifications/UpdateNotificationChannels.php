<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\NotificationChannelChoice;
use App\Enums\Notifications\NotificationAudienceKind;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Services\Notifications\NotificationChannelChoiceWriter;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\SystemNotificationChannels;
use Illuminate\Support\Facades\DB;

/**
 * Switches channels of one type for one audience, or for every audience the type reaches.
 */
final readonly class UpdateNotificationChannels
{
    public function __construct(
        private NotificationTypeCatalog $catalog,
        private NotificationChannelChoiceWriter $writer,
        private SystemNotificationChannels $channels,
    ) {}

    /**
     * @param  array<string, bool>  $choices  channel value => enabled
     */
    public function handle(User $editor, NotificationCode $code, ?NotificationAudienceKind $audience, array $choices): void
    {
        $type = $this->catalog->of($code);
        $audiences = $audience instanceof NotificationAudienceKind ? [$audience] : $type->audienceKinds();

        DB::transaction(function () use ($editor, $type, $audiences, $choices): void {
            foreach ($choices as $value => $enabled) {
                foreach ($audiences as $kind) {
                    $this->writer->write($editor, new NotificationChannelChoice($type, NotificationChannel::from($value), $kind, $enabled));
                }
            }
        });

        $this->channels->forget();
    }
}
