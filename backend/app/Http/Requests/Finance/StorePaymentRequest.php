<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\BankTransferOrder;
use App\Data\Finance\CardOrder;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Files\FileSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

final class StorePaymentRequest extends FormRequest
{
    public const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

    private const string RETIRED_PURPOSE = 'leadership';

    private const string BANK_TRANSFER = 'bank_transfer';

    public function authorize(): bool
    {
        $purpose = PaymentPurpose::tryFrom($this->string('purpose')->toString());
        $user = $this->user();

        return ! $purpose instanceof PaymentPurpose || ($user instanceof User && $user->can('createFor', [Payment::class, $purpose]));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in([...PaymentPurpose::values(), self::RETIRED_PURPOSE])],
            'channel' => ['sometimes', 'string', Rule::enum(PaymentChannel::class)],
            'award_cycle_id' => ['required_if:purpose,'.PaymentPurpose::Award->value, 'prohibited_unless:purpose,'.PaymentPurpose::Award->value, 'integer', 'min:1'],
            'bank_account_id' => ['required_if:channel,'.self::BANK_TRANSFER, 'prohibited_unless:channel,'.self::BANK_TRANSFER, 'uuid'],
            'proof' => [
                'required_if:channel,'.self::BANK_TRANSFER,
                'prohibited_unless:channel,'.self::BANK_TRANSFER,
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.app(FileSettings::class)->maxUploadKilobytes(),
            ],
        ];
    }

    public function purpose(): PaymentPurpose
    {
        return PaymentPurpose::tryFrom($this->string('purpose')->toString()) ?? throw PaymentRejected::purposeNotAvailable();
    }

    public function cardOrder(): CardOrder
    {
        return new CardOrder($this->purpose(), $this->idempotencyKey(), $this->awardCycleId());
    }

    public function awardCycleId(): ?int
    {
        return $this->filled('award_cycle_id') ? $this->integer('award_cycle_id') : null;
    }

    public function idempotencyKey(): string
    {
        return mb_strtolower((string) $this->header(self::IDEMPOTENCY_HEADER));
    }

    public function isBankTransfer(): bool
    {
        return $this->string('channel')->toString() === self::BANK_TRANSFER;
    }

    public function bankTransferOrder(): BankTransferOrder
    {
        $proof = $this->file('proof');

        if (! $proof instanceof UploadedFile) {
            throw new LogicException('The transfer receipt is validated before the order is built.');
        }

        return new BankTransferOrder($this->purpose(), $this->idempotencyKey(), mb_strtolower($this->string('bank_account_id')->toString()), $proof, $this->awardCycleId());
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header(self::IDEMPOTENCY_HEADER);

        if (! is_string($key) || ! Str::isUuid($key)) {
            throw PaymentRejected::idempotencyKeyMissing();
        }
    }
}
