<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Enums\Finance\PaymentPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckoutOptionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['nullable', 'string', Rule::enum(PaymentPurpose::class)],
            'award_cycle_id' => ['required_if:purpose,'.PaymentPurpose::Award->value, 'prohibited_unless:purpose,'.PaymentPurpose::Award->value, 'integer', 'min:1'],
        ];
    }

    public function purpose(): ?PaymentPurpose
    {
        return $this->enum('purpose', PaymentPurpose::class);
    }

    public function awardCycleId(): ?int
    {
        return $this->filled('award_cycle_id') ? $this->integer('award_cycle_id') : null;
    }
}
