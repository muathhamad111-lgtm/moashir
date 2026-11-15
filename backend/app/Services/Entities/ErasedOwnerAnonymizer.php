<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Enums\Identity\UserStatus;
use App\Models\Files\StoredFile;
use App\Models\Identity\OtpCode;
use App\Models\Identity\PasswordHistory;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use App\Models\Notifications\NotificationPreference;
use App\Services\Files\StoredFileRemover;
use App\Services\Identity\UserSessionTerminator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ErasedOwnerAnonymizer
{
    private const string ERASED_DOMAIN = '@erased.invalid';

    public function __construct(private UserSessionTerminator $sessions, private StoredFileRemover $remover) {}

    public function anonymize(User $owner, string $pseudonym): void
    {
        $this->sessions->terminateAll($owner);
        OtpCode::query()->where('user_id', $owner->id)->delete();
        PasswordHistory::query()->where('user_id', $owner->id)->delete();
        Notification::query()->where('user_id', $owner->id)->delete();
        NotificationPreference::query()->where('user_id', $owner->id)->delete();
        DB::table('password_reset_tokens')->whereRaw('lower(email) = ?', [mb_strtolower($owner->email)])->delete();
        $this->removeAvatarOf($owner);

        User::withoutAuditing(function () use ($owner, $pseudonym): void {
            $owner->syncRoles([]);
            $owner->forceFill([
                'name' => $pseudonym,
                'email' => Str::lower($pseudonym).self::ERASED_DOMAIN,
                'email_verified_at' => null,
                'phone' => null,
                'phone_verified_at' => null,
                'password' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'last_login_ip' => null,
                'status' => UserStatus::Suspended,
                'deactivated_at' => Date::now(),
                'deactivation_reason' => null,
                'avatar_file_id' => null,
            ])->save();
            $owner->delete();
        });
    }

    private function removeAvatarOf(User $owner): void
    {
        $avatar = $owner->avatar()->withTrashed()->first();

        if ($avatar instanceof StoredFile) {
            $this->remover->remove($avatar);
        }
    }
}
