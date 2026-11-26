<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\MyPaymentFilters;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Rules\EnumValueList;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;
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
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:status,purpose,method,channel,from,to'],
            'filter.status' => ['nullable', new EnumValueList(PaymentStatus::class)],
            'filter.purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
            'filter.method' => ['nullable', Rule::enum(PaymentInstrument::class)],
            'filter.channel' => ['nullable', Rule::enum(PaymentChannel::class)],
            'filter.from' => ['nullable', 'date_format:Y-m-d'],
            'filter.to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.from'],
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
            $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            PaymentInstrument::tryFrom($this->string('filter.method')->toString()),
            PaymentChannel::tryFrom($this->string('filter.channel')->toString()),
            $this->dateOf('filter.from'),
            $this->dateOf('filter.to'),
        );
    }

    private function dateOf(string $key): ?CarbonImmutable
    {
        return $this->filled($key) ? CarbonImmutable::createFromFormat('Y-m-d', $this->string($key)->toString())?->startOfDay() : null;
    }
}
