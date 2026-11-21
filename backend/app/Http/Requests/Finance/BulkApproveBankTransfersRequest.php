<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

final class BulkApproveBankTransfersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...self::selectionRules(),
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return list<string>
     */
    public function paymentIds(): array
    {
        return self::paymentIdsFrom($this);
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function selectionRules(): array
    {
        return [
            'payment_ids' => ['required', 'array', 'list', 'min:1', 'max:'.config()->integer('moashir.finance.bank_transfer.bulk_decision_max')],
            'payment_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function paymentIdsFrom(FormRequest $request): array
    {
        return array_values(array_map(static fn (mixed $id): string => mb_strtolower(is_string($id) ? $id : ''), $request->array('payment_ids')));
    }
}
