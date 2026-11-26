<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\NotificationChannelBulkChange;
use App\Data\Notifications\NotificationChannelChoice;
use App\Data\Notifications\NotificationType;
use App\Models\Identity\User;
use App\Services\Notifications\NotificationChannelChoiceWriter;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\SystemNotificationChannels;
use Illuminate\Support\Facades\DB;

/**
 * Switches channels on or off for many types at once. Core types and audiences a type never reaches are
 * skipped, so a bulk change can never silence a core notification. Every row it writes is audited.
 */
final readonly class BulkUpdateNotificationChannels
{
    public function __construct(
        private NotificationTypeCatalog $catalog,
        private NotificationChannelChoiceWriter $writer,
        private SystemNotificationChannels $channels,
    ) {}

    /**
     * @return int how many types it changed
     */
    public function handle(User $editor, NotificationChannelBulkChange $change): int
    {
        $types = array_values(array_filter(
            array_map($this->catalog->of(...), $change->codes),
            static fn (NotificationType $type): bool => ! $type->isCore,
        ));

        DB::transaction(function () use ($editor, $types, $change): void {
            foreach ($types as $type) {
                $this->writeType($editor, $type, $change);
            }
        });

        $this->channels->forget();

        return count($types);
    }

    private function writeType(User $editor, NotificationType $type, NotificationChannelBulkChange $change): void
    {
        $audiences = array_filter($change->audiences, $type->reachesKind(...));

        foreach ($change->channels as $channel) {
            foreach ($audiences as $audience) {
                $this->writer->write($editor, new NotificationChannelChoice($type, $channel, $audience, $change->enabled));
            }
        }
    }
}
