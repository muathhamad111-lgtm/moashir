<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\Audit\Audit;
use App\Models\Identity\User;
use App\Support\Audit\AuditValueRedactor;
use App\Support\Security\SensitiveUrl;

final readonly class AuditLogPresenter
{
    private const int USER_AGENT_LENGTH = 255;

    public function __construct(private AuditActorPresenter $actors) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Audit $audit, User $viewer): array
    {
        return [
            'id' => $audit->id,
            'event' => $audit->event,
            'actor' => $this->actors->present($audit, $viewer),
            'subject' => ['type' => AuditLogQuery::subjectTypeOf($audit->auditable_type), 'id' => $audit->auditable_id],
            'old_values' => AuditValueRedactor::redact($audit->old_values),
            'new_values' => AuditValueRedactor::redact($audit->new_values),
            'reason' => $audit->reason,
            'tags' => $audit->tags === null ? [] : explode(',', $audit->tags),
            'ip_address' => $audit->ip_address,
            'user_agent' => $audit->user_agent === null ? null : mb_substr($audit->user_agent, 0, self::USER_AGENT_LENGTH),
            'url' => $audit->url === null ? null : SensitiveUrl::redact($audit->url),
            'request_id' => $audit->request_id,
            'created_at' => $audit->created_at->toIso8601String(),
        ];
    }
}
