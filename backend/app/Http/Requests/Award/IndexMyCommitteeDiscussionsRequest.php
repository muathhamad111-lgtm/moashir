<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Enums\Award\CommitteeDiscussionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexMyCommitteeDiscussionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array:status'],
            'filter.status' => ['nullable', Rule::enum(CommitteeDiscussionStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function status(): ?CommitteeDiscussionStatus
    {
        return CommitteeDiscussionStatus::tryFrom($this->string('filter.status')->toString());
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
