<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\InvitationStatus;
use App\Enums\Identity\UserStatus;
use App\Models\Identity\UserInvitation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class ExpireInvitations
{
    public function handle(): int
    {
        $expired = 0;

        UserInvitation::query()
            ->with('user')
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '<=', Date::now())
            ->chunkById(100, function ($invitations) use (&$expired): void {
                foreach ($invitations as $invitation) {
                    DB::transaction(static function () use ($invitation): void {
                        $invitation->forceFill(['status' => InvitationStatus::Expired])->save();

                        if ($invitation->user->status === UserStatus::Invited) {
                            $invitation->user->forceFill(['status' => UserStatus::Expired])->save();
                        }
                    });
                    $expired++;
                }
            });

        return $expired;
    }
}
