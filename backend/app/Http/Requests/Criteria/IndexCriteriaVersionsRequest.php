<?php

declare(strict_types=1);

namespace App\Http\Requests\Criteria;

use App\Enums\Criteria\CriteriaVersionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexCriteriaVersionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', Rule::enum(CriteriaVersionStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function status(): ?CriteriaVersionStatus
    {
        $status = $this->input('filter.status');

        return is_string($status) ? CriteriaVersionStatus::tryFrom($status) : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
