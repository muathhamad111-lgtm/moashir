<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\TransactionFilters;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\TransactionSort;
use App\Enums\Integrations\IntegrationMode;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTransactionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:status,purpose,method,channel,from,to,mode,entity_id'],
            'filter.status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'filter.purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
            'filter.method' => ['nullable', Rule::enum(PaymentInstrument::class)],
            'filter.channel' => ['nullable', Rule::enum(PaymentChannel::class)],
            'filter.from' => ['nullable', 'date_format:Y-m-d'],
            'filter.to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filter.from'],
            'filter.mode' => ['nullable', Rule::enum(IntegrationMode::class)],
            'filter.entity_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(TransactionSort::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): TransactionFilters
    {
        return new TransactionFilters(
            $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            PaymentStatus::tryFrom($this->string('filter.status')->toString()),
            PaymentPurpose::tryFrom($this->string('filter.purpose')->toString()),
            PaymentInstrument::tryFrom($this->string('filter.method')->toString()),
            $this->dateOf('filter.from'),
            $this->dateOf('filter.to'),
            IntegrationMode::tryFrom($this->string('filter.mode')->toString()) ?? IntegrationMode::Live,
            TransactionSort::tryFrom($this->string('sort')->toString()) ?? TransactionSort::NewestFirst,
            $this->integer('per_page', 15),
            PaymentChannel::tryFrom($this->string('filter.channel')->toString()),
            $this->filled('filter.entity_id') ? $this->integer('filter.entity_id') : null,
        );
    }

    private function dateOf(string $key): ?CarbonImmutable
    {
        return $this->filled($key) ? CarbonImmutable::createFromFormat('Y-m-d', $this->string($key)->toString())?->startOfDay() : null;
    }
}
