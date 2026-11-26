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
use App\Services\Finance\BankAccountLimit;
use App\Services\Finance\BankAccountRowLock;
use App\Services\Finance\PaymentChannelsLock;
use App\Services\Settings\SettingsChangeReason;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ActivateBankAccount
{
    public const string VERB = 'activate';

    public function __construct(
        private PaymentChannelsLock $lock,
        private BankAccountRowLock $locks,
        private BankAccountLimit $limit,
        private SettingsChangeReason $reason,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, BankAccount $account, ?string $reason): BankAccount
    {
        $this->reason->record($actor, $reason);

        $activated = DB::transaction(function () use ($actor, $account): BankAccount {
            $this->lock->acquire();
            $locked = $this->locks->lock($account);

            if ($locked->is_active) {
                throw IllegalStateTransition::from('active', self::VERB);
            }

            $this->limit->assertRoomForOneMore();
            $locked->forceFill(['is_active' => true, 'activated_at' => Date::now(), 'deactivated_at' => null])->save();

            $this->audit->record(new AuditEntry(AuditEvent::BankAccountActivated, $locked, ['is_active' => false], ['is_active' => true]));
            BankAccountsChanged::dispatch($locked, BankAccountChange::Activated, $actor->id);

            return $locked;
        });

        $this->reason->alertIfSuperAdmin($actor);

        return $activated;
    }
}
