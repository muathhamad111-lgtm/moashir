<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\BankTransferOrder;
use App\Data\Finance\BankTransferSubmission;
use App\Data\Finance\CreatedPayment;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Finance\PaymentChannel;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use App\Services\Finance\BankTransferOrderOpener;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\PayingEntityGuard;
use App\Services\Finance\PaymentChannelAvailability;
use App\Services\Finance\PriceCalculator;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class SubmitBankTransferPayment
{
    private const string PROOF_FIELD = 'proof';

    public function __construct(
        private PayingEntityGuard $entities,
        private PaymentChannelAvailability $channels,
        private FinanceSettings $settings,
        private PriceCalculator $prices,
        private FileIntake $files,
        private StoredFileRemover $remover,
        private BankTransferOrderOpener $opener,
    ) {}

    public function handle(User $actor, BankTransferOrder $order): CreatedPayment
    {
        $replay = $this->replayOf($actor, $order);

        if ($replay instanceof Payment) {
            return $this->present($replay, true);
        }

        $entity = $this->payingEntity($actor, $order);
        $quote = $this->prices->quote($order->purpose);
        $submission = new BankTransferSubmission($order->purpose, $order->idempotencyKey, $order->bankAccountId, $quote, $this->acceptProof($actor, $order));

        try {
            return $this->present(DB::transaction(fn (): Payment => $this->opener->open($actor, $entity, $submission)), false);
        } catch (Throwable $failure) {
            $this->remover->remove($submission->proof);

            return $this->present($this->replayOf($actor, $order) ?? throw $failure, true);
        }
    }

    private function payingEntity(User $actor, BankTransferOrder $order): Entity
    {
        $entity = $this->entities->entityOf($actor);

        if (! $this->channels->isAvailable(PaymentChannel::BankTransfer)) {
            throw PaymentRejected::channelNotAvailable();
        }

        if (! in_array($order->purpose->value, $this->settings->linkedPaths(), true)) {
            throw PaymentRejected::purposeNotAvailable();
        }

        return $entity;
    }

    private function acceptProof(User $actor, BankTransferOrder $order): StoredFile
    {
        return $this->files->accept($order->proof, StoredFilePurpose::TransferProof, $actor, self::PROOF_FIELD);
    }

    private function replayOf(User $actor, BankTransferOrder $order): ?Payment
    {
        $payment = Payment::query()->where('initiated_by_user_id', $actor->id)->where('idempotency_key', $order->idempotencyKey)->first();

        if ($payment instanceof Payment && ($payment->purpose !== $order->purpose || ! $payment->isBankTransfer())) {
            throw PaymentRejected::idempotencyKeyReused();
        }

        return $payment;
    }

    private function present(Payment $payment, bool $isReplay): CreatedPayment
    {
        $payment->loadMissing(['entity', ...ShowMyPayment::RELATIONS]);

        return new CreatedPayment($payment, null, $isReplay, false);
    }
}
