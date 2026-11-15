<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Identity\EntityAccounts;
use App\Data\Identity\AccountAvatar;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\AvatarSource;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;

final readonly class AccountAvatarResolver
{
    public function __construct(private EntityAccounts $entityAccounts) {}

    public function of(User $user): ?AccountAvatar
    {
        return $user->isEntityUser() ? $this->entityLogoOf($user) : $this->photoOf($user);
    }

    private function entityLogoOf(User $user): ?AccountAvatar
    {
        $logo = $this->entityAccounts->logoOf($user);

        return $logo instanceof StoredFile ? new AccountAvatar($logo, AvatarSource::EntityLogo) : null;
    }

    private function photoOf(User $user): ?AccountAvatar
    {
        if ($user->avatar_file_id === null) {
            return null;
        }

        $photo = StoredFile::query()->whereKey($user->avatar_file_id)->ofPurpose(StoredFilePurpose::UserAvatar)->clean()->first();

        return $photo instanceof StoredFile ? new AccountAvatar($photo, AvatarSource::Photo) : null;
    }
}
