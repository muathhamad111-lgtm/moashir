<?php

declare(strict_types=1);

use App\Enums\Cms\CmsPageKey;
use App\Enums\Identity\TermsKind;
use App\Models\Cms\CmsPage;
use App\Models\Identity\TermsVersion;
use App\Services\Cms\LegalDocumentSource;
use App\Services\Demo\DemoLegalPageInstaller;
use App\Services\Demo\DemoTermsInstaller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('stores the legal drafts unpublished outside the demo and keeps the published versions untouched', function (): void {
    $terms = publishTerms(TermsKind::Terms, '2026-11');

    expect(Artisan::call('moashir:legal:install-drafts'))->toBe(0)
        ->and(Artisan::output())->toContain('2 unpublished legal text draft(s) and 1 unpublished legal page(s) stored');

    $drafts = TermsVersion::query()->drafts()->orderBy('kind')->get();
    $page = CmsPage::query()->where('key', CmsPageKey::Accessibility)->sole();

    expect($drafts->pluck('kind')->all())->toBe(['privacy', 'terms'])
        ->and($drafts->pluck('version')->unique()->all())->toBe([LegalDocumentSource::SEED_VERSION])
        ->and($drafts->firstOrFail()->title?->ar)->toBe('سياسة الخصوصية — منصة مؤشر')
        ->and($drafts->firstOrFail()->body->ar)->toStartWith('مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]')
        ->and($drafts->firstOrFail()->body->en)->toContain('## 17. AI assistant')
        ->and(TermsVersion::query()->current()->sole()->id)->toBe($terms->id)
        ->and($page->is_published)->toBeFalse()
        ->and($page->has_draft_changes)->toBeTrue()
        ->and($page->title?->en)->toBe('Accessibility Statement — Moashir')
        ->and($page->sections()->count())->toBe(13);

    $this->getJson('/api/v1/content/legal/terms')->assertOk()->assertJsonPath('data.version', '2026-11');
    $this->getJson('/api/v1/content/legal/privacy')->assertNotFound();
    $this->getJson('/api/v1/content/pages/accessibility')->assertNotFound()->assertJsonPath('code', 'cms.page_not_published');
});

it('is idempotent and never replaces a draft or page content prepared by the client', function (): void {
    $clientDraft = legalDraft(TermsKind::Privacy, '2027-01');
    $page = cmsPage(CmsPageKey::Accessibility);
    cmsSection(CmsPageKey::Accessibility, ['key' => 'statement']);

    Artisan::call('moashir:legal:install-drafts');
    expect(Artisan::output())->toContain('1 unpublished legal text draft(s) and 0 unpublished legal page(s) stored');
    Artisan::call('moashir:legal:install-drafts');
    expect(Artisan::output())->toContain('0 unpublished legal text draft(s) and 0 unpublished legal page(s) stored');

    expect(TermsVersion::query()->ofKind(TermsKind::Privacy)->sole()->id)->toBe($clientDraft->id)
        ->and(TermsVersion::query()->ofKind(TermsKind::Terms)->sole()->version)->toBe(LegalDocumentSource::SEED_VERSION)
        ->and($page->sections()->pluck('key')->all())->toBe(['statement']);
});

it('publishes the stored drafts as new versions over the earlier demo texts and the page draft on an installed demo', function (): void {
    $earlierTerms = publishTerms(TermsKind::Terms, 'demo-1');
    $earlierPrivacy = publishTerms(TermsKind::Privacy, 'demo-1');
    Artisan::call('moashir:legal:install-drafts');
    $drafts = TermsVersion::query()->drafts()->pluck('id')->sort()->values()->all();
    $editor = contentOfficer();

    $texts = app(DemoTermsInstaller::class)->install($editor);
    $pages = app(DemoLegalPageInstaller::class)->install($editor);

    expect($texts->published)->toBe(2)
        ->and(collect($texts->currentIds)->sort()->values()->all())->toBe($drafts)
        ->and($earlierTerms->refresh()->status()->value)->toBe('superseded')
        ->and($earlierPrivacy->refresh()->body->ar)->toBe('نص الشروط والأحكام المعتمد لمنصة مؤشر.')
        ->and(TermsVersion::query()->current()->pluck('published_by_user_id')->unique()->all())->toBe([$editor->id])
        ->and($pages)->toBe(1)
        ->and(cmsPage(CmsPageKey::Accessibility)->is_published)->toBeTrue()
        ->and(app(DemoTermsInstaller::class)->install($editor)->published)->toBe(0)
        ->and(app(DemoLegalPageInstaller::class)->install($editor))->toBe(0);
});

it('publishes a stored draft from the console and still creates and publishes new texts from files', function (): void {
    $draft = legalDraft(TermsKind::Privacy, '2026-12');
    $publisher = systemAdmin();

    $this->artisan('moashir:terms:publish', ['kind' => 'privacy', 'label' => '2026-12', '--publisher' => $publisher->email, '--effective-from' => '2026-12-01'])
        ->expectsOutputToContain('Published privacy version 2026-12')->assertSuccessful();

    expect($draft->refresh()->status()->value)->toBe('current')
        ->and($draft->effective_from?->toDateString())->toBe('2026-12-01')
        ->and($draft->published_by_user_id)->toBe($publisher->id);

    $arabic = temporaryListFile("# شروط الاستخدام — منصة مؤشر\n\nنص الشروط المحدّث لمنصة مؤشر.");
    $english = temporaryListFile("# Terms of Use — Moashir\n\nThe updated terms of the Moashir platform.");
    $this->artisan('moashir:terms:publish', ['kind' => 'terms', 'label' => '2027-01', '--ar' => $arabic, '--en' => $english, '--publisher' => $publisher->email])->assertSuccessful();

    $terms = TermsVersion::query()->current()->ofKind(TermsKind::Terms)->sole();
    expect($terms->title?->toArray())->toBe(['ar' => 'شروط الاستخدام — منصة مؤشر', 'en' => 'Terms of Use — Moashir'])
        ->and($terms->body->en)->toBe('The updated terms of the Moashir platform.');
});

it('refuses to publish a missing or already published draft and texts with HTML markup from the console', function (array $arguments): void {
    publishTerms(TermsKind::Terms, '2026-11');
    $publisher = systemAdmin();

    $this->artisan('moashir:terms:publish', ['kind' => 'terms', '--publisher' => $publisher->email, ...$arguments])->assertFailed();

    expect(TermsVersion::query()->count())->toBe(1);
})->with([
    'no stored draft' => [fn (): array => ['label' => '2026-12']],
    'published label' => [fn (): array => ['label' => '2026-11']],
    'markup in a file' => [fn (): array => ['label' => '2026-12', '--ar' => temporaryListFile('<script>alert(1)</script> نص الشروط.'), '--en' => temporaryListFile('The terms.')]],
    'only one language' => [fn (): array => ['label' => '2026-12', '--ar' => temporaryListFile('نص الشروط.')]],
]);
