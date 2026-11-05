<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\BankAccountChange;
use App\Events\Finance\BankAccountsChanged;
use App\Exceptions\IllegalStateTransition;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\BankAccountRetirementGuard;
use App\Services\Finance\BankAccountRowLock;
use App\Services\Finance\PaymentChannelsLock;
use App\Services\Settings\SettingsChangeReason;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateBankAccount
{
    public const string VERB = 'deactivate';

    public function __construct(
        private PaymentChannelsLock $lock,
        private BankAccountRowLock $locks,
        private BankAccountRetirementGuard $guard,
        private SettingsChangeReason $reason,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, BankAccount $account, ?string $reason): BankAccount
    {
        $this->reason->record($actor, $reason);

        $deactivated = DB::transaction(function () use ($actor, $account): BankAccount {
            $this->lock->acquire();
            $locked = $this->locks->lock($account);

            if (! $locked->is_active) {
                throw IllegalStateTransition::from('inactive', self::VERB);
            }

            $this->guard->assertMayRetire($locked);
            $locked->forceFill(['is_active' => false, 'deactivated_at' => Date::now()])->save();

            $this->audit->record(new AuditEntry(AuditEvent::BankAccountDeactivated, $locked, ['is_active' => true], ['is_active' => false]));
            BankAccountsChanged::dispatch($locked, BankAccountChange::Deactivated, $actor->id);

            return $locked;
        });

        $this->reason->alertIfSuperAdmin($actor);

        return $deactivated;
    }
}
