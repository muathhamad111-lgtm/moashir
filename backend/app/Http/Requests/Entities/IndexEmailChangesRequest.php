<?php

declare(strict_types=1);

namespace App\Http\Requests\Entities;

use App\Data\Entities\EmailChangeListFilters;
use App\Enums\Identity\EmailChangeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexEmailChangesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', Rule::in(['awaiting_approval', 'approved', 'rejected', 'applied', 'cancelled', 'expired', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): EmailChangeListFilters
    {
        $status = $this->input('filter.status');

        return new EmailChangeListFilters(
            is_string($status) ? EmailChangeStatus::tryFrom($status) : EmailChangeStatus::AwaitingApproval,
            $this->integer('per_page', 15),
        );
    }
}
