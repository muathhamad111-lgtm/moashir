<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Data\Finance\NewBankAccount;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\BankAccountChange;
use App\Events\Finance\BankAccountsChanged;
use App\Exceptions\Finance\BankAccountRejected;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\BankAccountLimit;
use App\Services\Finance\PaymentChannelsLock;
use App\Services\Settings\SettingsChangeReason;
use App\Support\Finance\BankAccountSnapshot;
use App\Support\ScalarValue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CreateBankAccount
{
    public function __construct(
        private PaymentChannelsLock $lock,
        private BankAccountLimit $limit,
        private SettingsChangeReason $reason,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, NewBankAccount $details, ?string $reason): BankAccount
    {
        $this->reason->record($actor, $reason);

        try {
            $account = DB::transaction(fn (): BankAccount => $this->create($actor, $details));
        } catch (UniqueConstraintViolationException) {
            throw BankAccountRejected::ibanTaken();
        }

        $this->reason->alertIfSuperAdmin($actor);

        return $account;
    }

    private function create(User $actor, NewBankAccount $details): BankAccount
    {
        $this->lock->acquire();
        $this->limit->assertRoomForOneMore();

        $account = BankAccount::query()->create([
            'bank_name' => $details->bankName,
            'account_holder_name' => $details->accountHolderName,
            'iban' => $details->iban->value,
            'account_number' => $details->accountNumber,
            'payer_note' => $details->payerNote,
            'display_order' => $this->nextDisplayOrder(),
            'is_active' => true,
            'activated_at' => Date::now(),
            'created_by_user_id' => $actor->id,
        ]);

        $this->audit->record(new AuditEntry(AuditEvent::BankAccountCreated, $account, [], BankAccountSnapshot::of($account)));
        BankAccountsChanged::dispatch($account, BankAccountChange::Added, $actor->id);

        return $account;
    }

    private function nextDisplayOrder(): int
    {
        return ScalarValue::int(BankAccount::query()->listed()->max('display_order')) + 1;
    }
}
