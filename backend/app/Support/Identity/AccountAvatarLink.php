<?php

declare(strict_types=1);

namespace App\Support\Identity;

use App\Data\Identity\AccountAvatar;

final class AccountAvatarLink
{
    public const string ROUTE = 'me.avatar.show';

    /**
     * @return array{source: string, url: string}|null
     */
    public static function of(?AccountAvatar $avatar): ?array
    {
        if (! $avatar instanceof AccountAvatar) {
            return null;
        }

        return [
            'source' => $avatar->source->value,
            'url' => route(self::ROUTE, ['v' => $avatar->file->public_id], false),
        ];
    }
}
