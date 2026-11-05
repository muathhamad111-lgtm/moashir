<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Data\Audit\AuditEntry;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Support\Audit\ActorRoles;
use App\Support\RequestContext;
use App\Support\Security\SensitiveUrl;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;

final readonly class AuditRecorder
{
    public function __construct(
        private AuthFactory $auth,
        private Request $request,
        private RequestContext $context,
    ) {}

    public function record(AuditEntry $entry): Audit
    {
        $actor = $this->auth->guard('web')->user();
        $actor = $actor instanceof User ? $actor : null;

        return Audit::query()->create([
            'user_type' => $actor?->getMorphClass(),
            'user_id' => $actor?->id,
            'event' => $entry->event->value,
            'auditable_type' => $entry->subject->getMorphClass(),
            'auditable_id' => $entry->subject->getKey(),
            'old_values' => $entry->oldValues,
            'new_values' => $entry->newValues,
            'url' => app()->runningInConsole() && ! app()->runningUnitTests() ? 'console' : SensitiveUrl::redact($this->request->url()),
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 1023),
            'tags' => $this->tagsFor($entry, $actor),
            'reason' => $this->context->reason(),
            'request_id' => $this->context->requestId(),
            'actor_roles' => $actor instanceof User ? ActorRoles::of($actor) : null,
            'actor_entity_id' => $actor?->entity_id,
        ]);
    }

    private function tagsFor(AuditEntry $entry, ?User $actor): ?string
    {
        $tags = $entry->tags;

        if ($actor?->isSuperAdmin() === true) {
            $tags[] = ActorRoles::SUPER_ADMIN_MARKER;
        }

        return $tags === [] ? null : implode(',', array_unique($tags));
    }
}
