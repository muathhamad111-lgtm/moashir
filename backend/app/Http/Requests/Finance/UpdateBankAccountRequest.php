<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Exceptions\Finance\BankAccountRejected;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBankAccountRequest extends FormRequest
{
    private const array PAYEE_FIELDS = ['bank_name', 'account_holder_name', 'iban', 'account_number'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...StoreBankAccountRequest::localizedRules('payer_note', 1, 300, false),
            'payer_note' => ['present', 'nullable', 'array:ar,en'],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function payerNote(): ?LocalizedText
    {
        return $this->filled('payer_note') ? LocalizedText::fromArray($this->array('payer_note')) : null;
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    protected function prepareForValidation(): void
    {
        if (array_intersect(self::PAYEE_FIELDS, array_keys($this->all())) !== []) {
            throw BankAccountRejected::payeeFieldsImmutable();
        }
    }
}
