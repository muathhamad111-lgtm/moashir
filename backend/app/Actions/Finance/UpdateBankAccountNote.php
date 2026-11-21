<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\BankAccountRowLock;
use App\Services\Settings\SettingsChangeReason;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\DB;

final readonly class UpdateBankAccountNote
{
    public function __construct(private BankAccountRowLock $locks, private SettingsChangeReason $reason, private AuditRecorder $audit) {}

    public function handle(User $actor, BankAccount $account, ?LocalizedText $payerNote, ?string $reason): BankAccount
    {
        $this->reason->record($actor, $reason);

        $updated = DB::transaction(function () use ($account, $payerNote): BankAccount {
            $locked = $this->locks->lock($account);
            $before = $locked->payer_note?->toArray();

            $locked->payer_note = $payerNote;
            $locked->save();

            $this->audit->record(new AuditEntry(AuditEvent::BankAccountUpdated, $locked, ['payer_note' => $before], ['payer_note' => $payerNote?->toArray()]));

            return $locked;
        });

        $this->reason->alertIfSuperAdmin($actor);

        return $updated;
    }
}
