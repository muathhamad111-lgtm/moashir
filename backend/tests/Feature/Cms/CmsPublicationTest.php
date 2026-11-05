<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\CmsSectionType;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Cms\CmsPage;
use App\Notifications\Cms\ContentNotification;
use Illuminate\Support\Facades\Notification;

const CMS_PUBLIC = '/api/v1/content';

beforeEach(function (): void {
    Notification::fake();
});

it('serves the maintenance payload while the homepage was never published', function (): void {
    cmsSection();

    $this->getJson(CMS_PUBLIC.'/home')->assertOk()->assertExactJson(['data' => ['published' => false, 'maintenance' => true], 'meta' => [], 'errors' => null]);
    $this->getJson(CMS_PUBLIC.'/landing')->assertOk()->assertJsonPath('data.maintenance', true);
    $this->getJson(CMS_PUBLIC.'/status')->assertOk()->assertJsonPath('data', ['maintenance' => true, 'published_at' => null]);
});

it('publishes the enabled sections and items only, with the site settings, and ends maintenance', function (): void {
    $hero = cmsSection(CmsPageKey::Home, ['key' => 'hero', 'type' => CmsSectionType::Hero, 'sort_order' => 1]);
    cmsItem($hero, ['sort_order' => 1]);
    cmsItem($hero, ['sort_order' => 2, 'is_enabled' => false, 'title' => ['ar' => 'عنصر مخفي', 'en' => 'Hidden item']]);
    cmsSection(CmsPageKey::Home, ['key' => 'draft', 'sort_order' => 2, 'is_enabled' => false]);
    $editor = contentOfficer();

    signIn($editor)->postJson('/api/v1/admin/content/pages/home/publish')
        ->assertOk()
        ->assertJsonPath('data.published', true)
        ->assertJsonPath('data.has_unpublished_changes', false);

    $this->getJson(CMS_PUBLIC.'/home')
        ->assertOk()
        ->assertJsonPath('data.published', true)
        ->assertJsonPath('data.maintenance', false)
        ->assertJsonCount(1, 'data.sections')
        ->assertJsonPath('data.sections.0.key', 'hero')
        ->assertJsonCount(1, 'data.sections.0.items')
        ->assertJsonPath('data.site_settings.featured_kpi_limit', 6)
        ->assertJsonMissingPath('data.sections.0.enabled');
    $this->getJson(CMS_PUBLIC.'/status')->assertJsonPath('data.maintenance', false);
    $this->getJson(CMS_PUBLIC.'/landing')->assertJsonPath('data.hero.title.en', 'About the Moashir platform');

    expect(Audit::query()->where('event', AuditEvent::CmsPagePublished->value)->count())->toBe(1);
});

it('keeps the published version until the next publication and then refreshes the cached copy', function (): void {
    $section = cmsSection();
    $editor = contentOfficer();
    publishPage(CmsPageKey::Home, $editor);
    $this->getJson(CMS_PUBLIC.'/home')->assertJsonPath('data.sections.0.title.ar', 'عن منصة مؤشر');

    $this->patchJson('/api/v1/admin/content/sections/'.$section->id, ['title' => ['ar' => 'عنوان جديد', 'en' => 'New title']])->assertOk();
    $this->getJson('/api/v1/admin/content/pages/home')->assertJsonPath('data.has_unpublished_changes', true);
    $this->getJson(CMS_PUBLIC.'/home')->assertJsonPath('data.sections.0.title.ar', 'عن منصة مؤشر');

    publishPage(CmsPageKey::Home, $editor);

    $this->getJson(CMS_PUBLIC.'/home')->assertJsonPath('data.sections.0.title', ['ar' => 'عنوان جديد', 'en' => 'New title']);
});

it('answers public reads from the cache until an explicit invalidation', function (): void {
    cmsSection();
    publishPage(CmsPageKey::Home, contentOfficer());
    $this->getJson(CMS_PUBLIC.'/home')->assertJsonPath('data.sections.0.key', fn (string $key): bool => $key !== '');

    CmsPage::query()->where('key', 'home')->update(['is_published' => false]);

    $this->getJson(CMS_PUBLIC.'/home')->assertJsonPath('data.published', true);
});

it('fills a missing translation from the other language on the public site only', function (): void {
    cmsSection(CmsPageKey::Home, ['title' => ['ar' => 'منصة مؤشر', 'en' => ''], 'description' => ['ar' => '', 'en' => 'English only description']]);
    $editor = contentOfficer();
    publishPage(CmsPageKey::Home, $editor);

    $this->getJson(CMS_PUBLIC.'/home', ['Accept-Language' => 'en'])
        ->assertJsonPath('data.sections.0.title', ['ar' => 'منصة مؤشر', 'en' => 'منصة مؤشر'])
        ->assertJsonPath('data.sections.0.description', ['ar' => 'English only description', 'en' => 'English only description']);
    $this->getJson('/api/v1/admin/content/home')->assertJsonPath('data.sections.0.title', ['ar' => 'منصة مؤشر', 'en' => '']);
});

it('unpublishes the homepage back to the maintenance page and notifies the content team', function (): void {
    cmsSection();
    $editor = contentOfficer();
    $otherOfficer = contentOfficer();
    $admin = systemAdmin();
    publishPage(CmsPageKey::Home, $editor);

    $this->postJson('/api/v1/admin/content/pages/home/unpublish')->assertOk()->assertJsonPath('data.published', false);

    $this->getJson(CMS_PUBLIC.'/home')->assertJsonPath('data.maintenance', true);
    $this->getJson(CMS_PUBLIC.'/status')->assertJsonPath('data.maintenance', true);
    Notification::assertSentTo([$otherOfficer, $admin], ContentNotification::class, static fn (ContentNotification $notification): bool => $notification->notice->code === NotificationCode::ContentUnpublished);
    Notification::assertSentTo([$otherOfficer, $admin], ContentNotification::class, static fn (ContentNotification $notification): bool => $notification->notice->code === NotificationCode::HomepageContentEdited);
    Notification::assertNotSentTo($editor, ContentNotification::class);
});

it('refuses to unpublish a page that is not published and to publish a page without enabled sections', function (): void {
    cmsSection(CmsPageKey::Lenses, ['is_enabled' => false]);
    signIn(contentOfficer());

    $this->postJson('/api/v1/admin/content/pages/lenses/unpublish')
        ->assertConflict()
        ->assertJsonPath('code', 'state.transition_illegal')
        ->assertJsonPath('errors.state', ['unpublished']);
    $this->postJson('/api/v1/admin/content/pages/lenses/publish')->assertUnprocessable()->assertJsonPath('code', 'cms.page_empty');
});

it('serves a published intro page and hides unpublished or unknown pages', function (): void {
    cmsSection(CmsPageKey::AccreditedIntro, ['key' => 'overview']);
    cmsSection(CmsPageKey::Lenses, ['key' => 'overview']);
    publishPage(CmsPageKey::AccreditedIntro, staffUser(RoleKey::SystemAdmin));

    $this->getJson(CMS_PUBLIC.'/pages/accredited_intro')
        ->assertOk()
        ->assertJsonPath('data.key', 'accredited_intro')
        ->assertJsonPath('data.sections.0.key', 'overview');
    $this->getJson(CMS_PUBLIC.'/pages/lenses')->assertNotFound()->assertJsonPath('code', 'cms.page_not_published');
    $this->getJson(CMS_PUBLIC.'/pages/privacy')->assertNotFound();
});

it('keeps the back office and sign-in reachable while the public site is in maintenance', function (): void {
    $this->getJson(CMS_PUBLIC.'/status')->assertJsonPath('data.maintenance', true);

    $this->getJson('/api/v1/auth/login-modes')->assertOk();
    signIn(contentOfficer())->getJson('/api/v1/admin/content/home')->assertOk();
});
