<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Identity\SignOut;
use App\Enums\Identity\AuthEventType;
use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use App\Services\Identity\SessionClock;
use App\Services\Identity\SessionTimeouts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceSessionTimeouts
{
    public function __construct(
        private SessionTimeouts $timeouts,
        private SessionClock $clock,
        private SignOut $signOut,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        if ($this->timeouts->hasExpired($user, $this->clock->signedInAt(), $this->clock->lastSeenAt(), Date::now())) {
            $this->signOut->handle($user, AuthEventType::SessionExpired);

            throw AuthenticationRejected::sessionExpired();
        }

        $this->clock->touch();

        return $next($request);
    }
}
