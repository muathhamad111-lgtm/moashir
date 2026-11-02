<?php

declare(strict_types=1);

namespace App\Http\Requests\Award;

use App\Support\ScalarValue;
use Illuminate\Foundation\Http\FormRequest;

final class SyncCycleInitialAssessorsRequest extends FormRequest
{
    private const int MAX_ASSESSORS = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assessor_ids' => ['present', 'array', 'max:'.self::MAX_ASSESSORS],
            'assessor_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<int>
     */
    public function assessorIds(): array
    {
        return array_values(array_map(static fn (mixed $id): int => ScalarValue::int($id), (array) $this->input('assessor_ids', [])));
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
