<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use App\Support\ValueObjects\LocalizedText;

/**
 * Sends NDA-01 (new version) or NDA-02 (reminder) to the in-scope users who have not accepted the version yet,
 * re-reading that list at sending time so a user who accepted in the meantime is skipped.
 */
final readonly class NdaNotifier
{
    private const int CHUNK = 200;

    private const string LINK = '/';

    public function __construct(private NdaAudience $audience) {}

    public function notifyPending(TermsVersion $version, NotificationCode $code): int
    {
        $notice = $this->notice($version, $code);
        $sent = 0;

        $this->audience->pending($version)->chunkById(self::CHUNK, static function ($users) use ($notice, &$sent): void {
            foreach ($users as $user) {
                /** @var User $user */
                $user->notify(new EntityNotification($notice));
                $sent++;
            }
        });

        return $sent;
    }

    private function notice(TermsVersion $version, NotificationCode $code): EntityNotice
    {
        $text = new NoticeText('notifications.nda.'.$code->value.'.staff', [
            'version' => $version->version,
            'changes' => $version->change_summary ?? new LocalizedText('', ''),
        ]);

        return EntityNotice::fromText($code, $text, self::LINK);
    }
}
