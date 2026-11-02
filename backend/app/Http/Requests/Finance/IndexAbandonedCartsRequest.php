<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\AbandonedCartFilters;
use App\Enums\Finance\PaymentPurpose;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAbandonedCartsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:purpose,contacted'],
            'filter.purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
            'filter.contacted' => ['nullable', Rule::in(['true', 'false'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): AbandonedCartFilters
    {
        $contacted = $this->input('filter.contacted');

        return new AbandonedCartFilters(
            PaymentPurpose::tryFrom($this->string('filter.purpose')->toString()),
            is_string($contacted) ? $contacted === 'true' : null,
            $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            $this->integer('per_page', 15),
        );
    }
}
