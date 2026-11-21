<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileRemover;
use Illuminate\Support\Facades\DB;

final readonly class RemoveOwnAvatar
{
    public function __construct(private StoredFileRemover $remover, private AuditRecorder $audit) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $photo = $locked->avatar()->withTrashed()->first();

            if ($photo instanceof StoredFile) {
                $this->detach($locked, $photo);
            }
        });

        $user->refresh();
    }

    private function detach(User $locked, StoredFile $photo): void
    {
        $locked->forceFill(['avatar_file_id' => null])->save();

        $this->audit->record(new AuditEntry(AuditEvent::UserAvatarRemoved, $locked, ['avatar_file_id' => $photo->public_id], ['avatar_file_id' => null]));

        DB::afterCommit(fn () => $this->remover->remove($photo));
    }
}
