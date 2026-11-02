<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Actions\Finance\ManuallySettlePayment;
use App\Data\Finance\ManualSettlement;
use App\Enums\Finance\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ManuallySettlePaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ManuallySettlePayment::allowedStatuses())],
            'provider_payment_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function settlement(): ManualSettlement
    {
        return new ManualSettlement(
            PaymentStatus::from($this->string('status')->toString()),
            $this->filled('provider_payment_id') ? $this->string('provider_payment_id')->toString() : null,
            trim($this->string('reason')->toString()),
        );
    }
}
