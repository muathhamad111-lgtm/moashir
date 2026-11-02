<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Actions\Identity\VerifyOtpCode;
use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\EmailChangeStatus;
use App\Enums\Identity\OtpPurpose;
use App\Events\Entities\EntityEmailChangeApplied;
use App\Events\Entities\EntityEmailChangeRequested;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Entities\Entity;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityEmailChangeApplier;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmEntityEmailChange
{
    public function __construct(
        private VerifyOtpCode $verifyOtp,
        private EntityEmailChangeApplier $applier,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $owner, string $challengeId, string $code): EmailChangeRequest
    {
        $otp = $this->verifyOtp->handle($challengeId, $code, OtpPurpose::EmailChange);
        $request = EmailChangeRequest::query()
            ->with('entity')
            ->where('otp_code_id', $otp->id)
            ->where('user_id', $owner->id)
            ->whereNotNull('entity_id')
            ->where('status', EmailChangeStatus::AwaitingOtp)
            ->first() ?? throw OtpRejected::challengeInvalid();

        DB::transaction(function () use ($request): void {
            $request->forceFill(['otp_verified_at' => Date::now()])->save();

            $request->policy === EmailChangePolicy::ImmediateNotify
                ? $this->applyImmediately($request)
                : $this->awaitApproval($request);
        });

        return $request->refresh();
    }

    private function applyImmediately(EmailChangeRequest $request): void
    {
        $this->applier->apply($request, null);

        EntityEmailChangeApplied::dispatch($request, null);
    }

    private function awaitApproval(EmailChangeRequest $request): void
    {
        $request->forceFill(['status' => EmailChangeStatus::AwaitingApproval])->save();

        if ($request->entity instanceof Entity) {
            $this->audit->record(new AuditEntry(
                AuditEvent::EntityEmailChangeRequested,
                $request->entity,
                ['email' => new EmailAddress($request->old_email)->masked()],
                ['email' => new EmailAddress($request->new_email)->masked(), 'policy' => $request->policy->value],
            ));
        }

        EntityEmailChangeRequested::dispatch($request);
    }
}
