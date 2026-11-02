<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\PrivacyRequestFilters;
use App\Enums\Entities\PrivacyRequestKind;
use App\Enums\Entities\PrivacyRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexPrivacyRequestsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status,kind,entity_id'],
            'filter.status' => ['nullable', Rule::enum(PrivacyRequestStatus::class)],
            'filter.kind' => ['nullable', Rule::enum(PrivacyRequestKind::class)],
            'filter.entity_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): PrivacyRequestFilters
    {
        $status = $this->input('filter.status');
        $kind = $this->input('filter.kind');
        $entityId = $this->input('filter.entity_id');

        return new PrivacyRequestFilters(
            is_string($status) ? PrivacyRequestStatus::tryFrom($status) : null,
            is_string($kind) ? PrivacyRequestKind::tryFrom($kind) : null,
            is_numeric($entityId) ? (int) $entityId : null,
            $this->integer('per_page', 15),
        );
    }
}
