<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StorePaymentRequest extends FormRequest
{
    public const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

    private const string RETIRED_PURPOSE = 'leadership';

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
        return ['purpose' => ['required', 'string', Rule::in([...PaymentPurpose::values(), self::RETIRED_PURPOSE])]];
    }

    public function purpose(): PaymentPurpose
    {
        return PaymentPurpose::tryFrom($this->string('purpose')->toString()) ?? throw PaymentRejected::purposeNotAvailable();
    }

    public function idempotencyKey(): string
    {
        return mb_strtolower((string) $this->header(self::IDEMPOTENCY_HEADER));
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header(self::IDEMPOTENCY_HEADER);

        if (! is_string($key) || ! Str::isUuid($key)) {
            throw PaymentRejected::idempotencyKeyMissing();
        }
    }
}
