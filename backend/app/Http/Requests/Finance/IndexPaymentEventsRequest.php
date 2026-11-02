<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\PaymentEventFilters;
use App\Enums\Finance\PaymentEventResult;
use App\Enums\Finance\PaymentEventSource;
use App\Enums\Integrations\IntegrationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPaymentEventsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:2', 'max:80'],
            'filter' => ['nullable', 'array:source,result,mode'],
            'filter.source' => ['nullable', Rule::enum(PaymentEventSource::class)],
            'filter.result' => ['nullable', Rule::enum(PaymentEventResult::class)],
            'filter.mode' => ['nullable', Rule::enum(IntegrationMode::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): PaymentEventFilters
    {
        return new PaymentEventFilters(
            PaymentEventSource::tryFrom($this->string('filter.source')->toString()),
            PaymentEventResult::tryFrom($this->string('filter.result')->toString()),
            IntegrationMode::tryFrom($this->string('filter.mode')->toString()),
            $this->filled('search') ? trim($this->string('search')->toString()) : null,
            $this->integer('per_page', 15),
        );
    }
}
