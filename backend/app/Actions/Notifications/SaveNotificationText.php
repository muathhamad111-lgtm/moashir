<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Notifications\NotificationTextChange;
use App\Models\Identity\User;
use App\Models\Notifications\NotificationTextOverride;
use App\Services\Notifications\Templates\NotificationTextsReloader;
use Illuminate\Support\Facades\DB;

final readonly class SaveNotificationText
{
    public function __construct(private NotificationTextsReloader $reloader) {}

    public function handle(User $editor, NotificationTextChange $change): NotificationTextOverride
    {
        $override = DB::transaction(static fn (): NotificationTextOverride => NotificationTextOverride::query()->updateOrCreate(
            ['template_key' => $change->template->key],
            ['code' => $change->template->code, 'parts' => $change->parts, 'updated_by_user_id' => $editor->id],
        ));

        $this->reloader->reload();

        return $override;
    }
}
