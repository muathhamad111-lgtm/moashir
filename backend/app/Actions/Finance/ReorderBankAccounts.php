<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Finance\BankAccount;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\PaymentChannelsLock;
use App\Services\Settings\SettingsChangeReason;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReorderBankAccounts
{
    public function __construct(private PaymentChannelsLock $lock, private SettingsChangeReason $reason, private AuditRecorder $audit) {}

    /**
     * @param  list<string>  $orderedPublicIds
     */
    public function handle(User $actor, array $orderedPublicIds, ?string $reason): void
    {
        $this->reason->record($actor, $reason);

        DB::transaction(function () use ($orderedPublicIds): void {
            $this->lock->acquire();
            $accounts = BankAccount::query()->listed()->lockForUpdate()->get()->keyBy('public_id');
            $this->assertComplete(array_values(array_map(ScalarValue::string(...), $accounts->keys()->all())), $orderedPublicIds);

            foreach ($orderedPublicIds as $index => $publicId) {
                $account = $accounts->get($publicId);

                if ($account instanceof BankAccount) {
                    $this->move($account, $index + 1);
                }
            }
        });

        $this->reason->alertIfSuperAdmin($actor);
    }

    /**
     * @param  list<string>  $known
     * @param  list<string>  $sent
     */
    private function assertComplete(array $known, array $sent): void
    {
        sort($known);
        sort($sent);

        if ($known !== $sent) {
            throw ValidationException::withMessages(['ordered_ids' => [__('validation.custom.ordered_ids.complete')]]);
        }
    }

    private function move(BankAccount $account, int $position): void
    {
        $before = $account->display_order;

        if ($before === $position) {
            return;
        }

        $account->forceFill(['display_order' => $position])->save();
        $this->audit->record(new AuditEntry(AuditEvent::BankAccountsReordered, $account, ['display_order' => $before], ['display_order' => $position]));
    }
}
