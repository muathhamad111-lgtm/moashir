<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Data\Finance\BankTransferFilters;
use App\Enums\Finance\BankTransferSort;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Rules\SearchableTerm;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexBankTransfersRequest extends FormRequest
{
    private const array STATUSES = ['under_review', 'paid', 'rejected', 'cancelled', 'refunded'];

    private const string ALL_STATUSES = 'all';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100', new SearchableTerm(2)],
            'filter' => ['nullable', 'array:status,purpose,bank_account,mode,frozen'],
            'filter.status' => ['nullable', Rule::in([...self::STATUSES, self::ALL_STATUSES])],
            'filter.purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
            'filter.bank_account' => ['nullable', 'uuid'],
            'filter.mode' => ['nullable', Rule::enum(IntegrationMode::class)],
            'filter.frozen' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(BankTransferSort::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function filters(): BankTransferFilters
    {
        return new BankTransferFilters(
            $this->filled('search') ? SearchTerm::tryFrom($this->string('search')->toString()) : null,
            $this->status(),
            PaymentPurpose::tryFrom($this->string('filter.purpose')->toString()),
            $this->filled('filter.bank_account') ? mb_strtolower($this->string('filter.bank_account')->toString()) : null,
            IntegrationMode::tryFrom($this->string('filter.mode')->toString()),
            $this->boolean('filter.frozen'),
            BankTransferSort::tryFrom($this->string('sort')->toString()) ?? BankTransferSort::OldestFirst,
            $this->integer('per_page', 15),
        );
    }

    private function status(): ?PaymentStatus
    {
        $value = $this->string('filter.status')->toString();

        return $value === self::ALL_STATUSES ? null : (PaymentStatus::tryFrom($value) ?? PaymentStatus::UnderReview);
    }
}
