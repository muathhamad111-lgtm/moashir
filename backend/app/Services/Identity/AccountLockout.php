<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountLockedNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final readonly class AccountLockout
{
    public function __construct(private AuthSettings $settings, private AuthenticationEventRecorder $events) {}

    public function recordFailure(User $user): bool
    {
        if (! $this->incrementAndLockIfExceeded($user)) {
            return false;
        }

        $this->events->forUser(AuthEventType::Locked, $user);
        $this->notifyLock($user);

        return true;
    }

    public function rejection(): AuthenticationRejected
    {
        return AuthenticationRejected::accountLocked($this->settings->lockoutMinutes() * 60);
    }

    private function incrementAndLockIfExceeded(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $attempts = $locked->failed_login_attempts + 1;
            $exceeded = $attempts >= $this->settings->lockoutMaxAttempts();

            $locked->forceFill([
                'failed_login_attempts' => $exceeded ? 0 : $attempts,
                'locked_until' => $exceeded ? Date::now()->addMinutes($this->settings->lockoutMinutes()) : $locked->locked_until,
            ])->save();

            return $exceeded;
        });
    }

    private function notifyLock(User $user): void
    {
        $notification = new AccountLockedNotification($user->id, $this->settings->lockoutMinutes());
        $administrators = User::query()->role(RoleKey::SystemAdmin->value)->whereKeyNot($user->id)->get();

        Notification::send($administrators->push($user), $notification);
    }
}
