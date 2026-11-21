<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const TRANSFER_ACCOUNTS = '/api/v1/finance/bank-transfers/accounts';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('lists the destination accounts for the queue filter with only the last 4 digits of the IBAN', function (): void {
    ['owner' => $owner, 'account' => $active] = transferPayingEntity();
    $inactive = bankAccount(['iban' => fictionalIban(1), 'display_order' => 30_001, 'is_active' => false, 'deactivated_at' => now()]);
    $retired = bankAccount([
        'iban' => fictionalIban(2),
        'bank_name' => ['ar' => 'مصرف الواحة (مثال)', 'en' => 'Al-Waha Bank (example)'],
    ]);
    approveTransfer(transferReviewer(), submittedTransfer($owner, $retired))->assertOk();
    switchTo(systemAdmin())->deleteJson('/api/v1/settings/payment/bank-accounts/'.$retired->public_id)->assertNoContent();

    $response = switchTo(transferReviewer())->getJson(TRANSFER_ACCOUNTS)
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('data', [
            ['id' => $active->public_id, 'bank_name' => ['ar' => 'بنك المثال (مثال)', 'en' => 'Example Bank (example)'], 'iban_last4' => '7519', 'state' => 'active'],
            ['id' => $inactive->public_id, 'bank_name' => ['ar' => 'بنك المثال (مثال)', 'en' => 'Example Bank (example)'], 'iban_last4' => substr(fictionalIban(1), -4), 'state' => 'inactive'],
            ['id' => $retired->public_id, 'bank_name' => ['ar' => 'مصرف الواحة (مثال)', 'en' => 'Al-Waha Bank (example)'], 'iban_last4' => substr(fictionalIban(2), -4), 'state' => 'archived'],
        ]);

    expect($response->getContent())->not->toContain(EXAMPLE_IBAN)->not->toContain(fictionalIban(1))->not->toContain(fictionalIban(2));
});

it('leaves out archived accounts that no transfer ever used', function (): void {
    $listed = bankAccount();
    bankAccount(['iban' => fictionalIban(3), 'is_active' => false, 'deactivated_at' => now(), 'archived_at' => now()]);

    switchTo(transferReviewer())->getJson(TRANSFER_ACCOUNTS)
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $listed->public_id);
});

it('lets operations viewers read the accounts and paginates up to 100 per page', function (): void {
    bankAccount(['display_order' => 1]);
    $second = bankAccount(['iban' => fictionalIban(4), 'display_order' => 2]);

    switchTo(systemAdmin())->getJson(TRANSFER_ACCOUNTS.'?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('meta.pagination', ['page' => 2, 'per_page' => 1, 'total' => 2, 'last_page' => 2])
        ->assertJsonPath('data.0.id', $second->public_id);
    $this->getJson(TRANSFER_ACCOUNTS.'?per_page=100')->assertOk()->assertJsonCount(2, 'data');
});

it('validates the pagination', function (string $query): void {
    switchTo(transferReviewer())->getJson(TRANSFER_ACCOUNTS.'?'.$query)->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
})->with(['per_page=101', 'per_page=0', 'page=0', 'per_page=all']);

it('keeps the accounts away from users without finance access', function (string $kind): void {
    bankAccount();
    $user = match ($kind) {
        'content officer' => contentOfficer(),
        'request follower' => requestFollower(),
        default => ownerOf(entityWithOwner()),
    };

    switchTo($user)->getJson(TRANSFER_ACCOUNTS)->assertForbidden();
})->with(['content officer', 'request follower', 'entity']);

it('refuses guests', function (): void {
    bankAccount();

    $this->getJson(TRANSFER_ACCOUNTS)->assertUnauthorized();
});
