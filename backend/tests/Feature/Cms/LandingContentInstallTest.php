<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\CmsSectionType;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\UserStatus;
use App\Models\Audit\Audit;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Cms\SiteSettings;
use Illuminate\Support\Facades\Notification;

const LANDING_KEYS = ['hero', 'stats', 'about', 'model', 'paths', 'journey', 'mid_cta', 'levels', 'kit', 'lenses', 'leaders', 'partners', 'start', 'contact', 'assistant'];

const LANDING_COMMAND = 'moashir:content:install-landing';

beforeEach(function (): void {
    Notification::fake();
});

function previousHomePage(): CmsSection
{
    $hero = cmsSection(CmsPageKey::Home, ['key' => 'hero', 'type' => CmsSectionType::Hero, 'sort_order' => 1]);
    cmsItem($hero, ['meta' => ['role' => 'point']]);
    cmsSection(CmsPageKey::Home, ['key' => 'how_it_works', 'type' => CmsSectionType::HowItWorks, 'sort_order' => 2]);

    return $hero;
}

/**
 * @return list<array{label: array{ar: string, en: string}, url: string}>
 */
function previousQuickLinks(): array
{
    return [
        ['label' => ['ar' => 'الرئيسية', 'en' => 'Home'], 'url' => '/'],
        ['label' => ['ar' => 'مسارات التقييم', 'en' => 'Assessment paths'], 'url' => '#paths'],
        ['label' => ['ar' => 'حقيبة التميز السريع', 'en' => 'Quick Excellence Kit'], 'url' => '/bag'],
        ['label' => ['ar' => 'مؤشرات الأداء', 'en' => 'Performance indicators'], 'url' => '/kpis'],
        ['label' => ['ar' => 'تواصل معنا', 'en' => 'Contact us'], 'url' => '#contact'],
    ];
}

it('replaces the previous home page with the landing content and publishes it without demo data', function (): void {
    config(['moashir.demo.enabled' => false]);
    $hero = previousHomePage();
    $officer = contentOfficer();

    $this->artisan(LANDING_COMMAND)
        ->expectsOutputToContain('15 sections created, 14 published')
        ->assertSuccessful();

    $home = cmsPage();
    expect($home->sections()->orderBy('sort_order')->pluck('key')->all())->toBe(LANDING_KEYS)
        ->and($home->sections()->where('key', 'partners')->value('is_enabled'))->toBeFalse()
        ->and(CmsSection::onlyTrashed()->whereKey($hero->id)->exists())->toBeTrue()
        ->and(CmsSection::onlyTrashed()->where('cms_page_id', $home->id)->count())->toBe(2)
        ->and(CmsSectionItem::onlyTrashed()->where('cms_section_id', $hero->id)->count())->toBe(1)
        ->and($home->is_published)->toBeTrue()
        ->and($home->published_by_user_id)->toBe($officer->id)
        ->and(Audit::query()->where('event', AuditEvent::CmsPagePublished->value)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::CmsSectionRemoved->value)->count())->toBe(2)
        ->and(Audit::query()->where('event', AuditEvent::CmsSectionCreated->value)->count())->toBe(15);
});

it('serves the new section types through the public home and hides the disabled partners', function (): void {
    contentOfficer();
    $this->artisan(LANDING_COMMAND)->assertSuccessful();

    $response = $this->getJson('/api/v1/content/home')
        ->assertOk()
        ->assertJsonPath('data.published', true)
        ->assertJsonPath('data.sections.0.key', 'hero')
        ->assertJsonPath('data.sections.0.items.0.meta.role', 'secondary-cta')
        ->assertJsonPath('data.sections.2.type', 'about')
        ->assertJsonPath('data.sections.2.meta.key_words.ar', 'نضج، وتميز، أثر، أعمق، ومستدام')
        ->assertJsonPath('data.sections.2.items.0.meta', ['role' => 'audience', 'glyph' => 'people'])
        ->assertJsonPath('data.sections.5.meta.variant', 'journey')
        ->assertJsonPath('data.sections.5.items.3.meta.tags', ['accredited', 'award'])
        ->assertJsonPath('data.sections.6.meta.variant', 'band')
        ->assertJsonPath('data.sections.7.type', 'levels')
        ->assertJsonPath('data.sections.7.meta.sample_score', 84)
        ->assertJsonPath('data.sections.13.key', 'assistant')
        ->assertJsonCount(14, 'data.sections');

    expect(array_column($response->json('data.sections'), 'key'))->not->toContain('partners');
});

it('does nothing on a second run unless forced', function (): void {
    contentOfficer();
    $this->artisan(LANDING_COMMAND)->assertSuccessful();
    $publishedAt = cmsPage()->published_at;

    $this->artisan(LANDING_COMMAND)
        ->expectsOutputToContain('nothing was changed')
        ->assertSuccessful();

    expect(CmsSection::onlyTrashed()->count())->toBe(0)
        ->and(cmsPage()->published_at?->equalTo($publishedAt))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::CmsPagePublished->value)->count())->toBe(1);

    $this->artisan(LANDING_COMMAND, ['--force' => true])
        ->expectsOutputToContain('15 sections created')
        ->assertSuccessful();

    expect(CmsSection::onlyTrashed()->count())->toBe(15)
        ->and(cmsPage()->sections()->count())->toBe(15)
        ->and(Audit::query()->where('event', AuditEvent::CmsPagePublished->value)->count())->toBe(2);
});

it('replaces the header and quick links only while they are empty or the shipped defaults', function (): void {
    contentOfficer();
    SiteSettings::current()->forceFill(['quick_links' => previousQuickLinks()])->save();

    $this->artisan(LANDING_COMMAND)
        ->expectsOutputToContain('links replaced: header_links, quick_links')
        ->assertSuccessful();

    $settings = SiteSettings::current();
    expect(array_column($settings->header_links, 'url'))->toBe(['/#model', '/#paths', '/#levels', '/#kit', '/#lenses', '/#contact'])
        ->and(array_column($settings->quick_links, 'url'))->toBe(['#model', '#paths', '#levels', '#kit', '#lenses', '#contact'])
        ->and(Audit::query()->where('event', AuditEvent::CmsSiteSettingsUpdated->value)->exists())->toBeTrue();
});

it('keeps links the content team has customised', function (): void {
    contentOfficer();
    $custom = [['label' => ['ar' => 'دليل الجهات', 'en' => 'Entity guide'], 'url' => '/help']];
    SiteSettings::current()->forceFill(['header_links' => $custom, 'quick_links' => $custom])->save();

    $this->artisan(LANDING_COMMAND)
        ->expectsOutputToContain('links replaced: none')
        ->assertSuccessful();

    expect(SiteSettings::current()->header_links)->toEqual($custom)
        ->and(SiteSettings::current()->quick_links)->toEqual($custom);
});

it('records the named editor, falls back to the System Administrator, and refuses ineligible editors', function (): void {
    $admin = systemAdmin();
    $finance = staffUser(RoleKey::FinanceOfficer);
    $suspended = contentOfficer();
    $suspended->forceFill(['status' => UserStatus::Suspended])->save();

    $this->artisan(LANDING_COMMAND, ['--editor' => $finance->email])
        ->expectsOutputToContain('does not belong to an active staff account')
        ->assertFailed();
    $this->artisan(LANDING_COMMAND, ['--editor' => $suspended->email])->assertFailed();
    $this->artisan(LANDING_COMMAND, ['--editor' => entityUser()->email])->assertFailed();
    $this->artisan(LANDING_COMMAND, ['--editor' => 'not-an-email'])->assertFailed();

    expect(cmsPage()->sections()->count())->toBe(0);

    $this->artisan(LANDING_COMMAND)->assertSuccessful();
    expect(cmsPage()->published_by_user_id)->toBe($admin->id);

    $officer = contentOfficer();
    $this->artisan(LANDING_COMMAND, ['--editor' => strtoupper($officer->email), '--force' => true])->assertSuccessful();
    expect(cmsPage()->published_by_user_id)->toBe($officer->id);
});

it('fails clearly and changes nothing when no editor can be resolved', function (): void {
    $hero = previousHomePage();

    $this->artisan(LANDING_COMMAND)
        ->expectsOutputToContain('no active Content Officer or System Administrator exists')
        ->assertFailed();

    expect(CmsSection::query()->whereKey($hero->id)->exists())->toBeTrue()
        ->and(cmsPage()->is_published)->toBeFalse();
});
