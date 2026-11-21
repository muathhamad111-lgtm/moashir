<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\TransferRejection;
use Illuminate\Foundation\Http\FormRequest;

final class BulkRejectBankTransfersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...BulkApproveBankTransfersRequest::selectionRules(), ...RejectBankTransferRequest::decisionRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['note.required_if' => __('validation.custom.rejection_reason.note_required')];
    }

    /**
     * @return list<string>
     */
    public function paymentIds(): array
    {
        return BulkApproveBankTransfersRequest::paymentIdsFrom($this);
    }

    public function rejection(): TransferRejection
    {
        return RejectBankTransferRequest::rejectionFrom($this);
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
