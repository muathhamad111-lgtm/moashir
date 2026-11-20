<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\IdentityChangeRequest;
use App\Services\Audit\AuditRecorder;

final readonly class ShowIdentityChange
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(IdentityChangeRequest $request): IdentityChangeRequest
    {
        $request->loadMissing(['user', 'submitter', 'reviewer']);

        if ($request->isPending() && $request->new_national_id !== null) {
            $this->audit->record(new AuditEntry(AuditEvent::IdentityChangeViewed, $request->user, [], ['request' => $request->public_id]));
        }

        return $request;
    }
}
