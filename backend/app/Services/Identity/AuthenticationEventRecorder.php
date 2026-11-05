<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\BlindIndexPurpose;
use App\Models\Identity\AuthenticationEvent;
use App\Models\Identity\User;
use App\Support\RequestContext;
use App\Support\ValueObjects\SignInIdentifier;
use Illuminate\Http\Request;

final readonly class AuthenticationEventRecorder
{
    public function __construct(private Request $request, private RequestContext $context, private BlindIndex $blindIndex) {}

    /**
     * @param  array<string, scalar>  $meta
     */
    public function forUser(AuthEventType $type, User $user, array $meta = []): AuthenticationEvent
    {
        return $this->store($type, ['user_id' => $user->id], $meta);
    }

    /**
     * @param  array<string, scalar>  $meta
     */
    public function forUnknownIdentifier(AuthEventType $type, SignInIdentifier $identifier, array $meta = []): AuthenticationEvent
    {
        return $this->store($type, [
            'identifier_type' => $identifier->type->value,
            'identifier_hash' => $this->blindIndex->of(BlindIndexPurpose::SignInIdentifier, $identifier->type->value.'|'.$identifier->value),
        ], $meta);
    }

    /**
     * @param  array<string, int|string>  $subject
     * @param  array<string, scalar>  $meta
     */
    private function store(AuthEventType $type, array $subject, array $meta): AuthenticationEvent
    {
        return AuthenticationEvent::query()->create([
            ...$subject,
            'type' => $type,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'request_id' => $this->context->requestId(),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
