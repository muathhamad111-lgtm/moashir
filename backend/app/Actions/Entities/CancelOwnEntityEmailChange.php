<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\EmailChangeStatus;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\OwnEntity;
use Illuminate\Support\Facades\DB;

final readonly class CancelOwnEntityEmailChange
{
    public function __construct(private OwnEntity $ownEntity, private AuditRecorder $audit) {}

    public function handle(User $owner): bool
    {
        $entity = $this->ownEntity->of($owner);

        return DB::transaction(function () use ($owner, $entity): bool {
            $open = EmailChangeRequest::query()
                ->where('user_id', $owner->id)
                ->whereIn('status', [EmailChangeStatus::AwaitingOtp, EmailChangeStatus::AwaitingApproval])
                ->lockForUpdate()
                ->get();

            if ($open->isEmpty()) {
                return false;
            }

            EmailChangeRequest::query()->whereKey($open->modelKeys())->update(['status' => EmailChangeStatus::Cancelled]);

            $this->audit->record(new AuditEntry(
                AuditEvent::EntityEmailChangeCancelled,
                $entity,
                ['status' => $this->previousStatusOf($open->all())->value],
                ['status' => EmailChangeStatus::Cancelled->value],
            ));

            return true;
        });
    }

    /**
     * @param  array<int, EmailChangeRequest>  $open
     */
    private function previousStatusOf(array $open): EmailChangeStatus
    {
        $awaitsApproval = array_any($open, static fn (EmailChangeRequest $request): bool => $request->isAwaitingApproval());

        return $awaitsApproval ? EmailChangeStatus::AwaitingApproval : EmailChangeStatus::AwaitingOtp;
    }
}
