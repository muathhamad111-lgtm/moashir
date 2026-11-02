<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\Identity\AuthEventType;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\SessionClock;
use App\Services\Notifications\SecurityAlerter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

final readonly class EstablishSession
{
    public function __construct(
        private AuthFactory $auth,
        private SessionClock $clock,
        private AuthenticationEventRecorder $events,
        private SecurityAlerter $alerter,
        private Request $request,
    ) {}

    public function handle(User $user, string $method): User
    {
        $this->auth->guard('web')->login($user);
        $this->clock->start();

        $user->forceFill([
            'last_login_at' => Date::now(),
            'last_login_ip' => $this->request->ip(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $this->events->forUser(
            $user->isSuperAdmin() ? AuthEventType::SuperAdminLogin : AuthEventType::LoginSucceeded,
            $user,
            ['method' => $method],
        );

        if ($user->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::SuperAdminLogin);
        }

        return $user;
    }
}
