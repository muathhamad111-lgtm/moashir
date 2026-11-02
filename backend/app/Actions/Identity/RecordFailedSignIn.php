<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use App\Notifications\Identity\AccountLockedNotification;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\AuthSettings;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final readonly class RecordFailedSignIn
{
    public function __construct(private AuthSettings $settings, private AuthenticationEventRecorder $events) {}

    public function handle(EmailAddress $email, ?User $user): never
    {
        if (! $user instanceof User) {
            $this->events->forUnknownEmail(AuthEventType::LoginFailed, $email->value);

            throw AuthenticationRejected::invalidCredentials();
        }

        $this->events->forUser(AuthEventType::LoginFailed, $user);

        if ($this->incrementAndLockIfExceeded($user)) {
            $this->events->forUser(AuthEventType::Locked, $user);
            $this->notifyLock($user);

            throw AuthenticationRejected::accountLocked($this->settings->lockoutMinutes() * 60);
        }

        throw AuthenticationRejected::invalidCredentials();
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
