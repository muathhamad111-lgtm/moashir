<?php

declare(strict_types=1);

namespace App\Http\Requests\Nda;

use App\Enums\Nda\NdaAcceptanceFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexNdaAcceptancesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::enum(NdaAcceptanceFilter::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filter(): NdaAcceptanceFilter
    {
        return NdaAcceptanceFilter::tryFrom($this->string('filter')->toString()) ?? NdaAcceptanceFilter::All;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
