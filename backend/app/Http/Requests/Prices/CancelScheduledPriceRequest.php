<?php

declare(strict_types=1);

namespace App\Http\Requests\Prices;

use App\Enums\Finance\PaymentPurpose;
use Illuminate\Foundation\Http\FormRequest;

final class CancelScheduledPriceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'min:10', 'max:1000']];
    }

    public function purpose(): PaymentPurpose
    {
        $key = $this->route('key');

        return PaymentPurpose::from(is_string($key) ? $key : '');
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
