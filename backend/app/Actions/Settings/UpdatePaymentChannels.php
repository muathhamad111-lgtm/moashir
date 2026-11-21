<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\Settings\PaymentChannelsUpdate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Settings\SettingKey;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Identity\User;
use App\Services\Finance\PaymentChannelAvailability;
use App\Services\Finance\PaymentChannelsLock;
use App\Services\Settings\SettingsChangeReason;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\DB;

final readonly class UpdatePaymentChannels
{
    public function __construct(
        private SettingsStore $store,
        private PaymentChannelsLock $lock,
        private PaymentChannelAvailability $availability,
        private SettingsChangeReason $reason,
    ) {}

    public function handle(User $actor, PaymentChannelsUpdate $update): void
    {
        $this->reason->record($actor, $update->reason);

        $changed = DB::transaction(function () use ($actor, $update): bool {
            $this->lock->acquire();
            $this->assertReady($update);

            return $this->store->put(SettingKey::PaymentChannels, $update->values(), AuditEvent::PaymentChannelsUpdated, $actor);
        });

        if ($changed) {
            $this->reason->alertIfSuperAdmin($actor);
        }
    }

    private function assertReady(PaymentChannelsUpdate $update): void
    {
        if ($update->includes(PaymentChannel::Electronic) && ! $this->availability->isGatewayReady()) {
            throw PaymentRejected::gatewayNotReady();
        }

        if ($update->includes(PaymentChannel::BankTransfer) && $this->availability->selectableBankAccountsCount() === 0) {
            throw PaymentRejected::bankAccountRequired();
        }
    }
}
