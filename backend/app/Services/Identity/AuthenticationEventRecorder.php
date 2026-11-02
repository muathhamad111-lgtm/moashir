<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AuthEventType;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\User;
use App\Support\RequestContext;
use Illuminate\Http\Request;

final readonly class AuthenticationEventRecorder
{
    public function __construct(private Request $request, private RequestContext $context) {}

    /**
     * @param  array<string, scalar>  $meta
     */
    public function forUser(AuthEventType $type, User $user, array $meta = []): AuthenticationEvent
    {
        return $this->store($type, $user->id, null, $meta);
    }

    public function forUnknownEmail(AuthEventType $type, string $emailAttempted): AuthenticationEvent
    {
        return $this->store($type, null, mb_substr(mb_strtolower($emailAttempted), 0, 254), []);
    }

    /**
     * @param  array<string, scalar>  $meta
     */
    private function store(AuthEventType $type, ?int $userId, ?string $emailAttempted, array $meta): AuthenticationEvent
    {
        return AuthenticationEvent::query()->create([
            'user_id' => $userId,
            'email_attempted' => $emailAttempted,
            'type' => $type,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'request_id' => $this->context->requestId(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
