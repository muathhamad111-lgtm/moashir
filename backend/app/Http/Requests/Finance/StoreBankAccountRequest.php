<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\NewBankAccount;
use App\Rules\FreeOfHtmlMarkup;
use App\Rules\SaudiIban;
use App\Support\ValueObjects\Iban;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBankAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...self::localizedRules('bank_name', 2, 120, true),
            ...self::localizedRules('account_holder_name', 2, 120, true),
            'iban' => ['bail', 'required', 'string', 'max:40', new SaudiIban, Rule::unique('bank_accounts', 'iban')],
            'account_number' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{6,30}$/'],
            ...self::localizedRules('payer_note', 1, 300, false),
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['iban.unique' => __('validation.custom.iban.unique')];
    }

    public function details(): NewBankAccount
    {
        return new NewBankAccount(
            LocalizedText::fromArray($this->array('bank_name')),
            LocalizedText::fromArray($this->array('account_holder_name')),
            new Iban($this->string('iban')->toString()),
            $this->filled('account_number') ? $this->string('account_number')->toString() : null,
            $this->filled('payer_note') ? LocalizedText::fromArray($this->array('payer_note')) : null,
        );
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function localizedRules(string $field, int $min, int $max, bool $isRequired): array
    {
        return [
            $field => [$isRequired ? 'required' : 'nullable', 'array:ar,en'],
            $field.'.ar' => ['required_with:'.$field, 'string', 'min:'.$min, 'max:'.$max, new FreeOfHtmlMarkup],
            $field.'.en' => ['required_with:'.$field, 'string', 'min:'.$min, 'max:'.$max, new FreeOfHtmlMarkup],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('iban'))) {
            $this->merge(['iban' => Iban::normalize($this->string('iban')->toString())]);
        }
    }
}
