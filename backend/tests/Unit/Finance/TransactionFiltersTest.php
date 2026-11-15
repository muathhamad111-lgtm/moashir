<?php

declare(strict_types=1);

use App\Data\Finance\TransactionFilters;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\TransactionType;
use App\Support\Finance\TransactionStatusFilter;

it('expands the awaiting payment alias and drops duplicates', function (): void {
    expect(TransactionStatusFilter::resolve(['awaiting_payment', 'created', 'paid']))
        ->toBe([PaymentStatus::Created, PaymentStatus::Pending, PaymentStatus::Paid]);
});

it('rebuilds stored export filters, including the older single status string', function (): void {
    expect(TransactionFilters::fromArray(['status' => 'paid'])->statuses)->toBe([PaymentStatus::Paid])
        ->and(TransactionFilters::fromArray(['status' => null])->statuses)->toBe([])
        ->and(TransactionFilters::fromArray(['status' => ['paid', 'refunded']])->statuses)->toBe([PaymentStatus::Paid, PaymentStatus::Refunded])
        ->and(TransactionFilters::fromArray(['type' => 'refund'])->types)->toBe([TransactionType::Refund])
        ->and(TransactionFilters::fromArray(['type' => null])->types)->toBe([])
        ->and(TransactionFilters::fromArray(['type' => ['refund', 'cash', 'electronic', 'refund']])->types)->toBe([TransactionType::Refund, TransactionType::Electronic]);

    $filters = new TransactionFilters(statuses: [PaymentStatus::UnderReview], types: [TransactionType::BankTransfer, TransactionType::Refund]);

    expect(TransactionFilters::fromArray($filters->toArray()))->toEqual($filters);
});

it('reads a type list once each, skipping unknown values', function (): void {
    expect(TransactionType::resolve(['bank_transfer', 'refund', 'bank_transfer', 'cash']))->toBe([TransactionType::BankTransfer, TransactionType::Refund]);
});

it('types a refunded payment as a refund whatever its channel', function (): void {
    expect(TransactionType::of(PaymentChannel::BankTransfer, PaymentStatus::Refunded))->toBe(TransactionType::Refund)
        ->and(TransactionType::of(PaymentChannel::BankTransfer, PaymentStatus::Paid))->toBe(TransactionType::BankTransfer)
        ->and(TransactionType::of(PaymentChannel::Electronic, PaymentStatus::Pending))->toBe(TransactionType::Electronic);
});
