<?php

declare(strict_types=1);

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\RoleKey;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Audit\Audit;
use App\Models\Identity\AuthenticationEvent;
use App\Services\Audit\AuditRecorder;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Support\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function recordedAudit(): Audit
{
    return app(AuditRecorder::class)->record(new AuditEntry(AuditEvent::UserUnlocked, systemAdmin()));
}

it('refuses to update an audit entry through the model', function (): void {
    $audit = recordedAudit();

    expect(fn () => $audit->forceFill(['event' => 'tampered'])->save())->toThrow(AppendOnlyViolation::class)
        ->and($audit->refresh()->event)->toBe(AuditEvent::UserUnlocked->value);
});

it('refuses to delete an audit entry through the model', function (): void {
    $audit = recordedAudit();

    expect(fn () => $audit->delete())->toThrow(AppendOnlyViolation::class)
        ->and(Audit::query()->whereKey($audit->id)->exists())->toBeTrue();
});

it('rejects raw SQL updates, deletes and truncation of the audit table in the database', function (): void {
    $audit = recordedAudit();

    expect(fn () => DB::transaction(fn () => DB::table('audits')->where('id', $audit->id)->update(['event' => 'tampered'])))->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::table('audits')->where('id', $audit->id)->delete()))->toThrow(QueryException::class, 'append_only')
        ->and(fn () => DB::transaction(fn () => DB::statement('TRUNCATE audits')))->toThrow(QueryException::class, 'append_only')
        ->and(DB::table('audits')->where('id', $audit->id)->value('event'))->toBe(AuditEvent::UserUnlocked->value);
});

it('applies the same protection to the authentication events', function (): void {
    $event = app(AuthenticationEventRecorder::class)->forUser(AuthEventType::LoginSucceeded, entityUser());

    expect(fn () => $event->delete())->toThrow(AppendOnlyViolation::class)
        ->and(fn () => DB::transaction(fn () => DB::table('authentication_events')->where('id', $event->id)->update(['type' => 'logout'])))->toThrow(QueryException::class, 'append_only')
        ->and(AuthenticationEvent::query()->whereKey($event->id)->value('type'))->toBe(AuthEventType::LoginSucceeded);
});

it('stores the actor, roles, request id and reason with every entry', function (): void {
    $administrator = systemAdmin();
    signIn($administrator);
    $target = staffUser(RoleKey::Assessor);

    $this->postJson("/api/v1/users/{$target->id}/suspend", ['reason' => 'انتهاء التكليف بنهاية دورة التقييم'], ['X-Request-Id' => '9b7e3f3a-2f8c-4d1e-8a5b-6c4d2e1f0a93'])->assertOk();

    $audit = Audit::query()->where('event', AuditEvent::UserSuspended->value)->firstOrFail();
    expect($audit->user_id)->toBe($administrator->id)
        ->and($audit->actor_roles)->toBe(['system_admin'])
        ->and($audit->request_id)->toBe('9b7e3f3a-2f8c-4d1e-8a5b-6c4d2e1f0a93')
        ->and($audit->reason)->toBe('انتهاء التكليف بنهاية دورة التقييم')
        ->and($audit->old_values)->toBe(['status' => 'active'])
        ->and($audit->new_values)->toBe(['status' => 'suspended']);
});

it('records model changes with before and after values and never the secrets', function (): void {
    $user = systemAdmin();
    $user->forceFill(['name' => 'سارة العتيبي', 'password' => 'Another-Riyadh-Season-2026'])->save();

    $audit = Audit::query()->where('auditable_id', $user->id)->where('event', 'updated')->latest('id')->firstOrFail();
    expect($audit->new_values)->toHaveKey('name')
        ->and($audit->new_values)->not->toHaveKey('password')
        ->and(json_encode($audit->getAttributes()))->not->toContain('two_factor_secret');
});

it('strips the query string from the audited URL', function (): void {
    signIn(systemAdmin());
    $target = staffUser(RoleKey::Assessor);

    $this->postJson("/api/v1/users/{$target->id}/unlock?email=person@moashir.example.sa")->assertConflict();
    $this->postJson("/api/v1/users/{$target->id}/suspend?token=secret-value", ['reason' => 'سبب إداري موثق للإيقاف'])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::UserSuspended->value)->value('url'))->not->toContain('secret-value');
});

it('keeps the reason scoped to a single request', function (): void {
    expect(app(RequestContext::class)->reason())->toBeNull();
});
