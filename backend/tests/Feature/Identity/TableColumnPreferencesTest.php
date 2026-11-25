<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Identity\UserTableColumns;

it('keeps the columns a user shows per table and returns them with the preferences', function (): void {
    signIn(staffUser(RoleKey::RequestFollower));

    $this->patchJson('/api/v1/me/preferences', ['table_columns' => ['requests-queue' => ['status', 'dueAt']]])
        ->assertOk()
        ->assertJsonPath('data.table_columns.requests-queue', ['status', 'dueAt']);

    $this->patchJson('/api/v1/me/preferences', ['table_columns' => ['finance-transactions' => ['total']]])->assertOk();

    $this->getJson('/api/v1/me/preferences')
        ->assertOk()
        ->assertJsonPath('data.table_columns.requests-queue', ['status', 'dueAt'])
        ->assertJsonPath('data.table_columns.finance-transactions', ['total']);
});

it('restores a table to its default columns when its choice is null', function (): void {
    $user = staffUser(RoleKey::RequestFollower);
    signIn($user);
    $this->patchJson('/api/v1/me/preferences', ['table_columns' => ['users-list' => ['role']]])->assertOk();

    $this->patchJson('/api/v1/me/preferences', ['table_columns' => ['users-list' => null]])
        ->assertOk()
        ->assertJsonMissingPath('data.table_columns.users-list');

    expect(UserTableColumns::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('keeps an empty choice: every optional column hidden', function (): void {
    signIn(staffUser(RoleKey::RequestFollower));

    $this->patchJson('/api/v1/me/preferences', ['table_columns' => ['users-list' => []]])
        ->assertOk()
        ->assertJsonPath('data.table_columns.users-list', []);
});

it('rejects tables and columns the platform does not list', function (array $payload, string $field): void {
    signIn(staffUser(RoleKey::RequestFollower));

    $this->patchJson('/api/v1/me/preferences', ['table_columns' => $payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown table' => [['secrets-table' => ['status']], 'table_columns'],
    'unknown column' => [['requests-queue' => ['password']], 'table_columns.requests-queue.0'],
    'not a list' => [['requests-queue' => 'status'], 'table_columns.requests-queue'],
    'repeated column' => [['requests-queue' => ['status', 'status']], 'table_columns.requests-queue.0'],
    'more columns than the table has' => [['users-list' => ['role', 'paths', 'status', 'role']], 'table_columns.users-list'],
]);

it('keeps one user choices away from another', function (): void {
    signIn(staffUser(RoleKey::RequestFollower));
    $this->patchJson('/api/v1/me/preferences', ['table_columns' => ['requests-queue' => ['status']]])->assertOk();

    switchTo(staffUser(RoleKey::FinanceOfficer));
    $this->getJson('/api/v1/me/preferences')->assertOk()->assertJsonPath('data.table_columns', []);
});

it('requires a signed-in user', function (): void {
    $this->patchJson('/api/v1/me/preferences', ['table_columns' => ['requests-queue' => ['status']]])->assertUnauthorized();
});
