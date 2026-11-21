<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderBankAccountsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'ordered_ids.*' => ['required', 'uuid', 'distinct'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<string>
     */
    public function orderedIds(): array
    {
        return array_values(array_map(static fn (mixed $id): string => mb_strtolower(is_string($id) ? $id : ''), $this->array('ordered_ids')));
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
