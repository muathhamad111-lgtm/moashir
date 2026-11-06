<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;

const AUDIT_LOGS = '/api/v1/audit-logs';

/**
 * @param  array<string, mixed>  $overrides
 */
function auditRow(?User $actor, object $subject, array $overrides = []): Audit
{
    return Audit::query()->create([
        'user_type' => $actor?->getMorphClass(),
        'user_id' => $actor?->id,
        'event' => 'updated',
        'auditable_type' => $subject::class,
        'auditable_id' => $subject->id,
        'old_values' => ['status' => 'active'],
        'new_values' => ['status' => 'suspended'],
        'url' => 'https://moashir.example.sa/api/v1/users/1',
        'ip_address' => '10.20.30.40',
        'user_agent' => 'Mozilla/5.0',
        ...$overrides,
    ]);
}

it('lists the audit trail newest first with the actor, the subject and pagination', function (): void {
    $admin = systemAdmin();
    $actor = staffUser(RoleKey::ContentOfficer);
    $entity = entityWithOwner();
    $older = auditRow($actor, $entity, ['event' => 'entity.updated', 'reason' => 'تصحيح بيانات الجهة بعد مراجعة السجل.', 'tags' => 'registration']);
    $newer = auditRow($actor, $entity, ['event' => 'entity.deactivated']);
    $baseline = Audit::query()->count();

    signIn($admin)->getJson(AUDIT_LOGS.'?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', $baseline)
        ->assertJsonPath('data.0.id', Audit::query()->max('id'))
        ->assertJsonCount(2, 'data');

    $this->getJson(AUDIT_LOGS.'?filter[subject_type]=entity&filter[subject_id]='.$entity->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1', [
            'id' => $older->id,
            'event' => 'entity.updated',
            'actor' => ['kind' => 'user', 'id' => $actor->id, 'name' => $actor->name, 'roles' => []],
            'subject' => ['type' => 'entity', 'id' => $entity->id],
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'suspended'],
            'reason' => 'تصحيح بيانات الجهة بعد مراجعة السجل.',
            'tags' => ['registration'],
            'ip_address' => '10.20.30.40',
            'user_agent' => 'Mozilla/5.0',
            'url' => 'https://moashir.example.sa/api/v1/users/1',
            'request_id' => null,
            'created_at' => $older->created_at->toIso8601String(),
        ]);
});

it('filters by date range, actor, event and address', function (): void {
    $admin = systemAdmin();
    $actor = staffUser(RoleKey::ContentOfficer);
    $entity = entityWithOwner();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00:00', 'Asia/Riyadh'));
    $october = auditRow($actor, $entity, ['event' => 'entity.updated', 'ip_address' => '10.0.0.7']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 23:30:00', 'Asia/Riyadh'));
    $november = auditRow($admin, $entity, ['event' => 'entity.deactivated']);
    $this->travelBack();
    signIn($admin);

    $ids = fn (string $query): array => array_column($this->getJson(AUDIT_LOGS.'?'.$query)->assertOk()->json('data'), 'id');

    expect($ids('filter[from]=2026-11-02&filter[to]=2026-11-02&filter[subject_type]=entity'))->toBe([$november->id])
        ->and($ids('filter[to]=2026-10-31&filter[subject_type]=entity'))->toBe([$october->id])
        ->and($ids('filter[actor_id]='.$actor->id))->toBe([$october->id])
        ->and($ids('filter[event]=entity.deactivated'))->toBe([$november->id])
        ->and($ids('filter[ip]=10.0.0.7'))->toBe([$october->id]);
});

it('never exposes secrets or single-use tokens stored in an audit row', function (): void {
    $entity = entityWithOwner();
    $row = auditRow(systemAdmin(), $entity, [
        'old_values' => ['password' => 'hash-value', 'integration' => ['secret_key' => 'sk_test_value', 'last4' => '1234']],
        'new_values' => ['two_factor_secret' => 'encrypted', 'link' => 'https://moashir.example.sa/auth/reset-password/'.str_repeat('a1', 32)],
        'url' => 'https://moashir.example.sa/api/v1/auth/invitations/'.str_repeat('b2', 32).'/accept',
    ]);

    $entry = signIn(systemAdmin())->getJson(AUDIT_LOGS.'/'.$row->id)->assertOk()->json('data');

    expect($entry['old_values'])->toEqual(['password' => '[redacted]', 'integration' => ['secret_key' => '[redacted]', 'last4' => '1234']])
        ->and($entry['new_values'])->toEqual(['two_factor_secret' => '[redacted]', 'link' => 'https://moashir.example.sa/auth/reset-password/[redacted]'])
        ->and($entry['url'])->toBe('https://moashir.example.sa/api/v1/auth/invitations/[redacted]/accept');
});

it('masks a Super Admin actor and hides rows about Super Admin accounts from the System Administrator', function (): void {
    $superAdmin = superAdmin();
    $admin = systemAdmin();
    $entity = entityWithOwner();
    $byOwner = auditRow($superAdmin, $entity, ['event' => 'entity.updated', 'reason' => 'تصحيح طارئ بطلب موثّق من صاحب الصلاحية.']);
    $aboutOwner = auditRow($admin, $superAdmin, ['event' => 'security.super_admin.email_changed']);

    signIn($admin)->getJson(AUDIT_LOGS.'/'.$byOwner->id)
        ->assertOk()
        ->assertJsonPath('data.actor', ['kind' => 'super_admin', 'id' => null, 'name' => null, 'roles' => ['super_admin']])
        ->assertJsonPath('data.reason', 'تصحيح طارئ بطلب موثّق من صاحب الصلاحية.');
    $this->getJson(AUDIT_LOGS.'/'.$aboutOwner->id)->assertNotFound();
    expect(array_column($this->getJson(AUDIT_LOGS.'?per_page=100')->json('data'), 'id'))->not->toContain($aboutOwner->id);

    $this->flushSession();
    app('auth')->forgetGuards();
    signIn($superAdmin)->getJson(AUDIT_LOGS.'/'.$aboutOwner->id)->assertOk();
    $this->getJson(AUDIT_LOGS.'/'.$byOwner->id)->assertJsonPath('data.actor.id', $superAdmin->id)->assertJsonPath('data.actor.kind', 'super_admin');
});

it('denies every reader without audit.view and asks guests to sign in', function (): void {
    $row = auditRow(null, entityWithOwner());

    foreach ([entityUser(), staffUser(RoleKey::FinanceOfficer), staffUser(RoleKey::RequestFollower)] as $reader) {
        $this->flushSession();
        app('auth')->forgetGuards();
        signIn($reader)->getJson(AUDIT_LOGS)->assertForbidden();
        $this->getJson(AUDIT_LOGS.'/'.$row->id)->assertForbidden();
        $this->postJson(AUDIT_LOGS.'/export')->assertForbidden();
    }

    $this->flushSession();
    app('auth')->forgetGuards();
    $this->getJson(AUDIT_LOGS)->assertUnauthorized();
});

it('exports the filtered trail as CSV, neutralises formulas and audits the export', function (): void {
    $admin = systemAdmin();
    $entity = entityWithOwner();
    auditRow($admin, $entity, ['event' => 'entity.updated', 'reason' => '=HYPERLINK("https://example.sa")']);

    $response = signIn($admin)->post(AUDIT_LOGS.'/export', ['filter' => ['subject_type' => 'entity', 'subject_id' => $entity->id]], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertHeader('X-Export-Row-Count', '1')
        ->assertDownload();
    $csv = $response->streamedContent();

    expect($csv)->toContain('entity.updated')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toStartWith("\u{FEFF}id,created_at,event");

    $export = Audit::query()->where('event', AuditEvent::AuditLogExported->value)->sole();
    expect($export->user_id)->toBe($admin->id)
        ->and($export->new_values)->toMatchArray(['row_count' => 1])
        ->and($export->tags)->toBe('export');
});

it('requires a reason from the Super Admin for an export', function (): void {
    signIn(superAdmin());

    $this->postJson(AUDIT_LOGS.'/export')->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    $this->post(AUDIT_LOGS.'/export', ['reason' => 'مراجعة دورية لسجل التدقيق بطلب من إدارة الأمن.'], ['Accept' => 'application/json'])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::AuditLogExported->value)->sole()->reason)->toBe('مراجعة دورية لسجل التدقيق بطلب من إدارة الأمن.');
});

it('refuses an export above the row limit', function (): void {
    config()->set('moashir.audit.export_max_rows', 1);
    $entity = entityWithOwner();
    auditRow(null, $entity);
    auditRow(null, $entity);

    signIn(systemAdmin())->postJson(AUDIT_LOGS.'/export', ['filter' => ['subject_type' => 'entity']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'export.too_large');
    expect(Audit::query()->where('event', AuditEvent::AuditLogExported->value)->exists())->toBeFalse();
});

it('validates the filters', function (string $query, string $field): void {
    signIn(systemAdmin())->getJson(AUDIT_LOGS.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'malformed date' => ['filter[from]=02-11-2026', 'filter.from'],
    'end before start' => ['filter[from]=2026-11-02&filter[to]=2026-11-01', 'filter.to'],
    'malformed address' => ['filter[ip]=10.0.0', 'filter.ip'],
    'unknown filter' => ['filter[url]=x', 'filter'],
    'page too large' => ['per_page=500', 'per_page'],
    'subject type with a namespace' => ['filter[subject_type]=App\\Models\\User', 'filter.subject_type'],
]);

it('keeps the audit rows append-only for the reader', function (): void {
    $row = auditRow(null, entityWithOwner());

    signIn(systemAdmin());
    $this->putJson(AUDIT_LOGS.'/'.$row->id, [])->assertStatus(405);
    $this->deleteJson(AUDIT_LOGS.'/'.$row->id)->assertStatus(405);
    expect(Audit::query()->whereKey($row->id)->exists())->toBeTrue();
});
