<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\AccountAvatar;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\AvatarSource;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileRemover;
use App\Services\Identity\UserAvatarIntake;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UploadOwnAvatar
{
    public function __construct(
        private UserAvatarIntake $intake,
        private StoredFileRemover $remover,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $user, UploadedFile $upload): AccountAvatar
    {
        $photo = $this->intake->accept($upload, $user);

        try {
            DB::transaction(fn () => $this->attach($user, $photo));
        } catch (Throwable $failure) {
            $this->remover->remove($photo);

            throw $failure;
        }

        $user->refresh();

        return new AccountAvatar($photo, AvatarSource::Photo);
    }

    private function attach(User $user, StoredFile $photo): void
    {
        $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $previous = $locked->avatar()->withTrashed()->first();
        $locked->forceFill(['avatar_file_id' => $photo->id])->save();

        $this->audit->record(new AuditEntry(AuditEvent::UserAvatarUploaded, $locked, ['avatar_file_id' => $previous?->public_id], [
            'avatar_file_id' => $photo->public_id,
            'mime_type' => $photo->mime_type,
            'size_bytes' => $photo->size_bytes,
            'sha256' => $photo->sha256,
        ]));

        if ($previous instanceof StoredFile) {
            DB::afterCommit(fn () => $this->remover->remove($previous));
        }
    }
}
