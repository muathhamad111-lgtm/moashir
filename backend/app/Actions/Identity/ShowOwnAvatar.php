<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Files\FileDownload;
use App\Data\Identity\AccountAvatar;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\StoredFileDisk;
use App\Services\Identity\AccountAvatarResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class ShowOwnAvatar
{
    private const string FILE_STEM = 'avatar.';

    public function __construct(private AccountAvatarResolver $avatars, private StoredFileDisk $disk) {}

    public function handle(User $user): FileDownload
    {
        $avatar = $this->avatars->of($user);

        if (! $avatar instanceof AccountAvatar) {
            throw (new ModelNotFoundException)->setModel(StoredFile::class);
        }

        $file = $avatar->file;

        return new FileDownload($this->disk->readStream($file), self::FILE_STEM.($file->extension ?? 'bin'), $file->mime_type, $file->size_bytes);
    }
}
