<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Models\Audit\Audit;
use App\Models\Contact\ContactMessage;
use App\Models\Identity\TermsVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

const LEGAL_ADMIN = '/api/v1/admin/content/legal';

beforeEach(function (): void {
    Notification::fake();
});

function publishLegalDraft(TermsVersion $draft): void
{
    signIn(contentOfficer())->postJson(LEGAL_ADMIN.'/versions/'.$draft->id.'/publish')->assertOk();
}

it('serves the current published version to guests with structured blocks in both languages', function (string $locale): void {
    $draft = legalDraft();
    publishLegalDraft($draft);

    asGuest()->withHeaders(['Accept-Language' => $locale])->getJson('/api/v1/content/legal/privacy')
        ->assertOk()
        ->assertJsonPath('data.id', $draft->id)
        ->assertJsonPath('data.kind', 'privacy')
        ->assertJsonPath('data.version', '2026-12')
        ->assertJsonPath('data.effective_from', now('Asia/Riyadh')->toDateString())
        ->assertJsonPath('data.published_at', fn (string $value): bool => $value !== '')
        ->assertJsonPath('data.title', ['ar' => 'سياسة الخصوصية — منصة مؤشر', 'en' => 'Privacy Policy — Moashir'])
        ->assertJsonPath('data.body.ar.0', ['type' => 'paragraph', 'text' => 'مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]'])
        ->assertJsonPath('data.body.ar.1', ['type' => 'heading', 'level' => 2, 'text' => '1. مقدمة'])
        ->assertJsonPath('data.body.en.3', ['type' => 'list', 'ordered' => false, 'start' => null, 'items' => ['visitors of the platform;', 'registered entities.']])
        ->assertJsonPath('data.body.en.4.header', ['Data category', 'Retention period'])
        ->assertJsonPath('data.body.en.4.rows.0', ['"Contact us" messages', '24 months']);
})->with(['ar', 'en']);

it('falls back to the default title for a version published without one', function (): void {
    publishTerms(TermsKind::Terms);

    asGuest()->getJson('/api/v1/content/legal/terms')->assertOk()
        ->assertJsonPath('data.title', ['ar' => 'شروط الاستخدام', 'en' => 'Terms of use'])
        ->assertJsonPath('data.body.ar', [['type' => 'paragraph', 'text' => 'نص الشروط والأحكام المعتمد لمنصة مؤشر.']]);
});

it('answers 404 while no version is published, for a draft only and for an unknown kind', function (): void {
    legalDraft(TermsKind::Terms);

    asGuest()->getJson('/api/v1/content/legal/terms')->assertNotFound()->assertJsonPath('code', 'legal.not_published');
    asGuest()->getJson('/api/v1/content/legal/privacy')->assertNotFound()->assertJsonPath('code', 'legal.not_published');
    asGuest()->getJson('/api/v1/content/legal/cookies')->assertNotFound();
});

it('lets the Content Officer create, edit and publish a draft that replaces the current version, with an audit trail', function (): void {
    $previous = publishTerms(TermsKind::Privacy, '2026-11');
    asGuest()->getJson('/api/v1/content/legal/privacy')->assertJsonPath('data.version', '2026-11');
    $editor = contentOfficer();

    $id = signIn($editor)->postJson(LEGAL_ADMIN.'/privacy/versions', legalPayload())->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null)
        ->assertJsonPath('data.body.en', LEGAL_BODY_EN)
        ->json('data.id');

    $this->getJson(LEGAL_ADMIN.'/privacy/versions')->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.1.status', 'current')
        ->assertJsonMissingPath('data.0.body')
        ->assertJsonPath('meta.pagination.total', 2);
    $this->putJson(LEGAL_ADMIN.'/versions/'.$id, ['version' => '2027-01', 'title' => ['ar' => 'سياسة الخصوصية المحدّثة', 'en' => 'Updated privacy policy']])->assertOk()
        ->assertJsonPath('data.version', '2027-01')
        ->assertJsonPath('data.title.en', 'Updated privacy policy')
        ->assertJsonPath('data.body.ar', LEGAL_BODY_AR);
    asGuest()->getJson('/api/v1/content/legal/privacy')->assertJsonPath('data.version', '2026-11');

    signIn($editor)->postJson(LEGAL_ADMIN.'/versions/'.$id.'/publish')->assertOk()
        ->assertJsonPath('data.status', 'current')
        ->assertJsonPath('data.effective_from', now('Asia/Riyadh')->toDateString());

    asGuest()->getJson('/api/v1/content/legal/privacy')->assertOk()->assertJsonPath('data.version', '2027-01')->assertJsonPath('data.title.ar', 'سياسة الخصوصية المحدّثة');
    expect($previous->refresh()->status()->value)->toBe('superseded')
        ->and(TermsVersion::query()->current()->ofKind(TermsKind::Privacy)->sole()->id)->toBe($id)
        ->and(Audit::query()->whereIn('event', [AuditEvent::TermsDraftCreated->value, AuditEvent::TermsDraftUpdated->value, AuditEvent::TermsPublished->value])->pluck('event')->all())
        ->toBe(['terms.draft_created', 'terms.draft_updated', 'terms.published']);

    $published = Audit::query()->where('event', AuditEvent::TermsPublished->value)->sole();
    expect($published->new_values)->toMatchArray(['current_version' => '2027-01', 'kind' => 'privacy'])
        ->and($published->old_values)->toBe(['current_version' => '2026-11'])
        ->and($published->new_values['body_sha256']['en'])->toBe(hash('sha256', LEGAL_BODY_EN));
});

it('keeps a published version immutable through the API and in the database', function (): void {
    $draft = legalDraft();
    publishLegalDraft($draft);
    $editor = contentOfficer();

    switchTo($editor)->putJson(LEGAL_ADMIN.'/versions/'.$draft->id, ['title' => ['ar' => 'عنوان آخر', 'en' => 'Another title']])
        ->assertConflict()->assertJsonPath('code', 'legal.version_not_draft')->assertJsonPath('errors.version_status', ['current']);
    $this->deleteJson(LEGAL_ADMIN.'/versions/'.$draft->id)->assertConflict();
    $this->postJson(LEGAL_ADMIN.'/versions/'.$draft->id.'/publish')->assertConflict();

    expect(static fn () => DB::transaction(static fn () => DB::table('terms_versions')->where('id', $draft->id)->update(['body' => json_encode(['ar' => 'نص معدّل', 'en' => 'Changed text'])])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(static fn () => DB::transaction(static fn () => DB::table('terms_versions')->where('id', $draft->id)->update(['published_at' => null, 'published_by_user_id' => null, 'effective_from' => null, 'is_current' => false])))
        ->toThrow(QueryException::class, 'append_only')
        ->and(static fn () => DB::transaction(static fn () => DB::table('terms_versions')->where('id', $draft->id)->delete()))
        ->toThrow(QueryException::class, 'append_only')
        ->and($draft->refresh()->body->en)->toBe(LEGAL_BODY_EN)
        ->and($draft->is_current)->toBeTrue();
});

it('allows a single open draft per kind and a version label only once per kind', function (): void {
    publishTerms(TermsKind::Privacy, '2026-11');
    $editor = contentOfficer();

    signIn($editor)->postJson(LEGAL_ADMIN.'/privacy/versions', legalPayload(['version' => '2026-11']))
        ->assertUnprocessable()->assertJsonPath('code', 'legal.version_taken')->assertJsonValidationErrors('version');
    $this->postJson(LEGAL_ADMIN.'/privacy/versions', legalPayload())->assertCreated();
    $this->postJson(LEGAL_ADMIN.'/privacy/versions', legalPayload(['version' => '2027-01']))->assertConflict()->assertJsonPath('code', 'legal.draft_exists');
    $this->postJson(LEGAL_ADMIN.'/terms/versions', legalPayload(['version' => '2026-11']))->assertCreated();

    expect(TermsVersion::query()->drafts()->count())->toBe(2);
});

it('validates the draft input and refuses HTML markup', function (array $overrides, string $field): void {
    signIn(contentOfficer())->postJson(LEGAL_ADMIN.'/terms/versions', legalPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(TermsVersion::query()->exists())->toBeFalse();
})->with([
    'invalid label' => [['version' => 'الإصدار الأول'], 'version'],
    'missing label' => [['version' => null], 'version'],
    'missing English text' => [['body' => ['ar' => LEGAL_BODY_AR]], 'body.en'],
    'blank Arabic text' => [['body' => ['ar' => "\u{200B}\u{202E} ", 'en' => LEGAL_BODY_EN]], 'body.ar'],
    'unknown language' => [['body' => ['ar' => LEGAL_BODY_AR, 'en' => LEGAL_BODY_EN, 'fr' => 'Texte']], 'body'],
    'script in the body' => [['body' => ['ar' => LEGAL_BODY_AR."\n\n<script>alert(1)</script>", 'en' => LEGAL_BODY_EN]], 'body.ar'],
    'tag in the title' => [['title' => ['ar' => 'سياسة <b>الخصوصية</b>', 'en' => 'Privacy']], 'title.ar'],
    'too long text' => [['body' => ['ar' => str_repeat('نص ', 20001), 'en' => LEGAL_BODY_EN]], 'body.ar'],
]);

it('stores the sanitized text without control or override characters', function (): void {
    $id = signIn(contentOfficer())->postJson(LEGAL_ADMIN.'/terms/versions', legalPayload([
        'title' => ['ar' => "شروط\u{202E} الاستخدام\n", 'en' => "Terms\tof use"],
        'body' => ['ar' => "\u{FEFF}نص الشروط\r\n\r\n\r\n\r\n- بند أول\u{0007}", 'en' => 'Terms text'],
    ]))->assertCreated()->json('data.id');

    $version = TermsVersion::query()->findOrFail($id);
    expect($version->title?->toArray())->toBe(['ar' => 'شروط الاستخدام', 'en' => 'Terms of use'])
        ->and($version->body->ar)->toBe("نص الشروط\n\n- بند أول");
});

it('discards a draft and audits it', function (): void {
    $draft = legalDraft(TermsKind::Terms);

    signIn(contentOfficer())->deleteJson(LEGAL_ADMIN.'/versions/'.$draft->id)->assertNoContent();

    expect(TermsVersion::query()->exists())->toBeFalse()
        ->and(Audit::query()->where('event', AuditEvent::TermsDraftDiscarded->value)->sole()->old_values)->toMatchArray(['kind' => 'terms', 'version' => '2026-12']);
});

it('denies the legal version management to other roles and to guests', function (): void {
    $draft = legalDraft();
    $requests = [
        ['getJson', LEGAL_ADMIN.'/privacy/versions'],
        ['postJson', LEGAL_ADMIN.'/terms/versions'],
        ['getJson', LEGAL_ADMIN.'/versions/'.$draft->id],
        ['putJson', LEGAL_ADMIN.'/versions/'.$draft->id],
        ['deleteJson', LEGAL_ADMIN.'/versions/'.$draft->id],
        ['postJson', LEGAL_ADMIN.'/versions/'.$draft->id.'/publish'],
    ];

    foreach ([staffUser(RoleKey::FinanceOfficer), staffUser(RoleKey::Assessor), ownerOf(entityWithOwner())] as $user) {
        foreach ($requests as [$method, $uri]) {
            switchTo($user)->{$method}($uri, legalPayload())->assertForbidden();
        }
    }

    foreach ($requests as [$method, $uri]) {
        asGuest()->{$method}($uri, legalPayload())->assertUnauthorized();
    }

    switchTo(systemAdmin())->getJson(LEGAL_ADMIN.'/privacy/versions')->assertOk();
    expect($draft->refresh()->isDraft())->toBeTrue();
});

it('requires a reason from the Super Admin to publish and stores it in the audit log', function (): void {
    $draft = legalDraft();
    $superAdmin = superAdmin();

    signIn($superAdmin)->postJson(LEGAL_ADMIN.'/versions/'.$draft->id.'/publish')
        ->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    expect($draft->refresh()->isDraft())->toBeTrue();

    switchTo($superAdmin)->postJson(LEGAL_ADMIN.'/versions/'.$draft->id.'/publish', ['reason' => 'اعتماد النص بعد مراجعة الإدارة القانونية للجهة المالكة.'])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::TermsPublished->value)->sole()->reason)->toBe('اعتماد النص بعد مراجعة الإدارة القانونية للجهة المالكة.');
});

it('keeps consent at registration and in contact messages on the current version', function (): void {
    $terms = publishTerms(TermsKind::Terms, '2026-11');
    $oldPrivacy = publishTerms(TermsKind::Privacy, '2026-11');
    $newPrivacy = legalDraft();
    publishLegalDraft($newPrivacy);

    asGuest()->postJson('/api/v1/auth/register', registrationPayload(['terms_version_id' => $terms->id, 'privacy_version_id' => $oldPrivacy->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('privacy_version_id');
    asGuest()->postJson('/api/v1/auth/register', registrationPayload(['terms_version_id' => $terms->id, 'privacy_version_id' => $newPrivacy->id]))->assertCreated();
    submitContactAsGuest()->assertCreated();

    expect(ContactMessage::query()->sole()->privacy_terms_version_id)->toBe($newPrivacy->id)
        ->and($newPrivacy->acceptances()->count())->toBe(1)
        ->and($oldPrivacy->acceptances()->count())->toBe(0);
});
