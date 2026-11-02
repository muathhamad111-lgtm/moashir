<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\RefundFilters;
use App\Enums\Finance\PaymentPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexRefundsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:from,to,purpose'],
            'filter.from' => ['nullable', 'date_format:Y-m-d'],
            'filter.to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.from'],
            'filter.purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): RefundFilters
    {
        return new RefundFilters(
            $this->dateOf('filter.from'),
            $this->dateOf('filter.to'),
            PaymentPurpose::tryFrom($this->string('filter.purpose')->toString()),
            $this->integer('per_page', 15),
        );
    }

    private function dateOf(string $key): ?CarbonImmutable
    {
        return $this->filled($key) ? CarbonImmutable::createFromFormat('Y-m-d', $this->string($key)->toString())?->startOfDay() : null;
    }
}
