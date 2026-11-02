<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;

it('exports the filtered entities as CSV and audits the export without personal data', function (): void {
    $active = entityWithOwner(static fn ($factory) => $factory->named('جمعية الأمل الخيرية', 'Al-Amal Charitable Association'));
    entityWithOwner(static fn ($factory) => $factory->deactivated());
    $admin = systemAdmin();
    signIn($admin);

    $response = $this->postJson('/api/v1/entities/export', ['filter' => ['status' => 'active']])
        ->assertOk()
        ->assertHeader('X-Export-Row-Count', '1');

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($csv))));

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('entities-')
        ->and($csv)->toStartWith("\u{FEFF}")
        ->and($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('الرقم المرجعي')
        ->and($lines[1])->toContain($active->reference())
        ->and($lines[1])->toContain('جمعية الأمل الخيرية')
        ->and($lines[1])->toContain(ownerOf($active)->email);

    $audit = Audit::query()->where('event', AuditEvent::EntitiesExported->value)->firstOrFail();
    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->new_values)->toMatchArray(['row_count' => 1])
        ->and(json_encode($audit->new_values))->not->toContain(ownerOf($active)->email);
});

it('writes the column headers in English for an English request', function (): void {
    entityWithOwner();
    signIn(systemAdmin());
    $this->withHeaders(['Accept-Language' => 'en']);

    expect($this->postJson('/api/v1/entities/export')->assertOk()->streamedContent())->toContain('Reference,"Name in Arabic"');
});

it('neutralizes spreadsheet formulas in exported cells', function (): void {
    entityWithOwner(static fn ($factory) => $factory->named('=HYPERLINK("http://example.sa")', '+SUM(A1)'));
    signIn(systemAdmin());

    $csv = $this->postJson('/api/v1/entities/export')->assertOk()->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain("'+SUM(A1)");
});

it('denies the export without the export permission', function (RoleKey $role): void {
    signIn(staffUser($role));

    $this->postJson('/api/v1/entities/export')->assertForbidden();
})->with([RoleKey::RequestFollower, RoleKey::FinanceOfficer, RoleKey::Assessor]);

it('validates the export filters', function (): void {
    signIn(systemAdmin());

    $this->postJson('/api/v1/entities/export', ['filter' => ['status' => 'closed']])->assertUnprocessable();
});

it('refuses an export above the row limit', function (): void {
    config(['moashir.entities.export_max_rows' => 1]);
    entityWithOwner();
    entityWithOwner();
    signIn(systemAdmin());

    $this->postJson('/api/v1/entities/export')->assertUnprocessable()->assertJsonPath('code', 'export.too_large')->assertJsonPath('meta.limit', 1);
});

it('rate limits exports per user', function (): void {
    signIn(systemAdmin());

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/entities/export')->assertOk();
    }

    $this->postJson('/api/v1/entities/export')->assertTooManyRequests();
});
