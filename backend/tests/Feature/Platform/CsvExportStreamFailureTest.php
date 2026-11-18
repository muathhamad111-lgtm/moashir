<?php

declare(strict_types=1);

use App\Contracts\Platform\DownloadStreamOpener;
use App\Models\Identity\User;

beforeEach(function (): void {
    app()->instance(DownloadStreamOpener::class, new class implements DownloadStreamOpener
    {
        public function open(): mixed
        {
            return false;
        }
    });
});

it('answers a safe service error instead of an empty CSV when the export stream cannot be opened', function (string $method, string $uri, User $actor): void {
    $response = signIn($actor)->json($method, $uri)
        ->assertStatus(503)
        ->assertJsonPath('code', 'export.stream_unavailable')
        ->assertJsonPath('message', __('errors.export.stream_unavailable'));

    expect($response->headers->get('Content-Disposition'))->toBeNull()
        ->and($response->headers->get('X-Export-Row-Count'))->toBeNull();
})->with([
    'entities' => ['POST', '/api/v1/entities/export', fn (): User => systemAdmin()],
    'users' => ['GET', '/api/v1/users/export', fn (): User => systemAdmin()],
    'audit logs' => ['POST', '/api/v1/audit-logs/export', fn (): User => systemAdmin()],
    'transactions' => ['POST', '/api/v1/finance/transactions/export', fn (): User => financeOfficer()],
]);
