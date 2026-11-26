<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\TransferRejection;
use App\Enums\Finance\TransferRejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RejectBankTransferRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::decisionRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['note.required_if' => __('validation.custom.rejection_reason.note_required')];
    }

    public function rejection(): TransferRejection
    {
        return self::rejectionFrom($this);
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decisionRules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', Rule::enum(TransferRejectionReason::class)],
            'note' => ['nullable', 'required_if:rejection_reason,'.TransferRejectionReason::Other->value, 'string', 'min:10', 'max:500'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public static function rejectionFrom(FormRequest $request): TransferRejection
    {
        return new TransferRejection(
            TransferRejectionReason::from($request->string('rejection_reason')->toString()),
            $request->filled('note') ? $request->string('note')->trim()->toString() : null,
        );
    }
}
