<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\TransactionFilters;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentInstrument;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\TransactionSort;
use App\Enums\Finance\TransactionType;
use App\Enums\Integrations\IntegrationMode;
use App\Rules\SearchableTerm;
use App\Support\Finance\TransactionStatusFilter;
use App\Support\ScalarValue;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTransactionsRequest extends FormRequest
{
    private const array LIST_FILTERS = ['status', 'type'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:type,status,purpose,method,channel,from,to,mode,entity_id'],
            'filter.type' => ['nullable', 'list', 'max:'.count(TransactionType::cases())],
            'filter.type.*' => ['required', 'string', Rule::in(TransactionType::values())],
            'filter.status' => ['nullable', 'list', 'max:'.count(TransactionStatusFilter::values())],
            'filter.status.*' => ['required', 'string', Rule::in(TransactionStatusFilter::values())],
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
            search: $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            statuses: TransactionStatusFilter::resolve($this->listOf('filter.status')),
            purpose: PaymentPurpose::tryFrom($this->string('filter.purpose')->toString()),
            method: PaymentInstrument::tryFrom($this->string('filter.method')->toString()),
            from: $this->dateOf('filter.from'),
            to: $this->dateOf('filter.to'),
            mode: IntegrationMode::tryFrom($this->string('filter.mode')->toString()) ?? IntegrationMode::Live,
            sort: TransactionSort::tryFrom($this->string('sort')->toString()) ?? TransactionSort::NewestFirst,
            perPage: $this->integer('per_page', 15),
            channel: PaymentChannel::tryFrom($this->string('filter.channel')->toString()),
            entityId: $this->filled('filter.entity_id') ? $this->integer('filter.entity_id') : null,
            types: TransactionType::resolve($this->listOf('filter.type')),
        );
    }

    /**
     * A single `filter[status]=paid` or `filter[type]=refund` is still accepted and read as a one-value list.
     */
    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter');

        if (! is_array($filter)) {
            return;
        }

        foreach (self::LIST_FILTERS as $key) {
            if (is_string($filter[$key] ?? null)) {
                $filter[$key] = [$filter[$key]];
            }
        }

        $this->merge(['filter' => $filter]);
    }

    /**
     * @return list<string>
     */
    private function listOf(string $key): array
    {
        return array_values(array_map(static fn (mixed $value): string => ScalarValue::string($value), $this->array($key)));
    }

    private function dateOf(string $key): ?CarbonImmutable
    {
        return $this->filled($key) ? CarbonImmutable::createFromFormat('Y-m-d', $this->string($key)->toString())?->startOfDay() : null;
    }
}
