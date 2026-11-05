<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\MyPaymentFilters;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Rules\EnumValueList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexMyPaymentsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status,purpose'],
            'filter.status' => ['nullable', new EnumValueList(PaymentStatus::class)],
            'filter.purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): MyPaymentFilters
    {
        return new MyPaymentFilters(
            EnumValueList::parse($this->string('filter.status')->toString(), PaymentStatus::class),
            PaymentPurpose::tryFrom($this->string('filter.purpose')->toString()),
            $this->integer('per_page', 15),
        );
    }
}
