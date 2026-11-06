<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\BankAccountChange;
use App\Events\Finance\BankAccountsChanged;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\BankAccountRetirementGuard;
use App\Services\Finance\BankAccountRowLock;
use App\Services\Finance\PaymentChannelsLock;
use App\Services\Settings\SettingsChangeReason;
use App\Support\Finance\BankAccountSnapshot;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class DeleteBankAccount
{
    public function __construct(
        private PaymentChannelsLock $lock,
        private BankAccountRowLock $locks,
        private BankAccountRetirementGuard $guard,
        private SettingsChangeReason $reason,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, BankAccount $account, ?string $reason): void
    {
        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($actor, $account): void {
            $this->lock->acquire();
            $locked = $this->locks->lock($account);
            $this->guard->assertMayRetire($locked);
            $before = BankAccountSnapshot::of($locked);

            if ($locked->transfers()->exists()) {
                $this->archive($locked, $before);
            } else {
                $locked->delete();
                $this->audit->record(new AuditEntry(AuditEvent::BankAccountDeleted, $locked, $before, []));
            }

            BankAccountsChanged::dispatch($locked, BankAccountChange::Deleted, $actor->id);
        });

        $this->reason->alertIfSuperAdmin($actor);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function archive(BankAccount $locked, array $before): void
    {
        $now = Date::now();
        $locked->forceFill(['is_active' => false, 'deactivated_at' => $locked->deactivated_at ?? $now, 'archived_at' => $now])->save();

        $this->audit->record(new AuditEntry(AuditEvent::BankAccountArchived, $locked, $before, BankAccountSnapshot::of($locked)));
    }
}
