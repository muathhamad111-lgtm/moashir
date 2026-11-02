<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyPaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['provider_payment_id' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/']];
    }

    public function providerPaymentId(): string
    {
        return $this->string('provider_payment_id')->toString();
    }
}
