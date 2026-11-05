<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\NotificationPreferenceChange;
use App\Data\Notifications\NotificationPreferenceRow;
use App\Exceptions\Notifications\NotificationPreferenceRejected;
use App\Models\Identity\User;
use App\Models\Notifications\NotificationPreference;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Notifications\NotificationTypeCatalog;
use App\Services\Notifications\UserNotificationAudiences;

final readonly class UpdateOwnNotificationPreferences
{
    public function __construct(
        private NotificationTypeCatalog $catalog,
        private UserNotificationAudiences $audiences,
        private ListOwnNotificationPreferences $listPreferences,
    ) {}

    /**
     * @param  list<NotificationPreferenceChange>  $changes
     * @return list<NotificationPreferenceRow>
     */
    public function handle(User $user, array $changes): array
    {
        $this->assertChangeable($user, $changes);

        NotificationPreference::query()->upsert($this->rows($user, $changes), ['user_id', 'code', 'channel'], ['is_enabled', 'updated_at']);

        return $this->listPreferences->handle($user);
    }

    /**
     * @param  list<NotificationPreferenceChange>  $changes
     */
    private function assertChangeable(User $user, array $changes): void
    {
        $audiences = $this->audiences->of($user);

        foreach ($changes as $index => $change) {
            $type = $this->catalog->of($change->code);

            if (! $type->reaches($audiences)) {
                throw NotificationPreferenceRejected::notApplicable($index);
            }

            if (! $type->allowsOptOut($change->channel)) {
                throw NotificationPreferenceRejected::locked($index);
            }
        }
    }

    /**
     * @param  list<NotificationPreferenceChange>  $changes
     * @return list<array{user_id: int, code: string, channel: string, is_enabled: bool}>
     */
    private function rows(User $user, array $changes): array
    {
        $rows = [];

        foreach ($changes as $change) {
            $rows[NotificationPreferences::key($change->code, $change->channel)] = [
                'user_id' => $user->id,
                'code' => $change->code->value,
                'channel' => $change->channel->value,
                'is_enabled' => $change->enabled,
            ];
        }

        return array_values($rows);
    }
}
