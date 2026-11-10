<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\CmsSectionType;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use Illuminate\Support\Facades\Notification;

const CMS_ADMIN = '/api/v1/admin/content';

beforeEach(function (): void {
    Notification::fake();
});

it('lets the Content Officer and the System Administrator read the homepage editor', function (RoleKey $role): void {
    $section = cmsSection(CmsPageKey::Home, ['key' => 'hero', 'type' => CmsSectionType::Hero]);
    cmsItem($section);
    signIn(staffUser($role));

    $this->getJson(CMS_ADMIN.'/home')
        ->assertOk()
        ->assertJsonPath('data.key', 'home')
        ->assertJsonPath('data.published', false)
        ->assertJsonPath('data.has_unpublished_changes', true)
        ->assertJsonPath('data.sections.0.key', 'hero')
        ->assertJsonPath('data.sections.0.type', 'hero')
        ->assertJsonPath('data.sections.0.items.0.title', ['ar' => 'التقييم الذاتي', 'en' => 'Self assessment'])
        ->assertJsonPath('data.site_settings.featured_kpi_limit', 6)
        ->assertJsonPath('meta.total', 1);
})->with([RoleKey::ContentOfficer, RoleKey::SystemAdmin]);

it('denies the content editor to roles without content.home.manage', function (RoleKey $role): void {
    $section = cmsSection();
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role));

    $this->getJson(CMS_ADMIN.'/home')->assertForbidden();
    $this->patchJson(CMS_ADMIN.'/sections/'.$section->id, ['title' => ['ar' => 'عنوان']])->assertForbidden();
    $this->postJson(CMS_ADMIN.'/pages/home/publish')->assertForbidden();
    $this->patchJson(CMS_ADMIN.'/site-settings', ['show_featured_kpis' => false])->assertForbidden();
})->with([RoleKey::FinanceOfficer, RoleKey::CriteriaOfficer, RoleKey::Entity]);

it('asks guests to sign in before editing content', function (): void {
    $this->getJson(CMS_ADMIN.'/home')->assertUnauthorized();
    $this->postJson(CMS_ADMIN.'/images')->assertUnauthorized();
});

it('creates a section at the end of the page, audits it and marks the draft as changed', function (): void {
    cmsSection(CmsPageKey::Home, ['key' => 'hero', 'sort_order' => 1]);
    $officer = contentOfficer();

    $response = signIn($officer)->postJson(CMS_ADMIN.'/pages/home/sections', [
        'key' => 'partners',
        'type' => 'partners',
        'title' => ['ar' => 'شركاء النجاح', 'en' => 'Success partners'],
        'cta_url' => '/partners',
        'icon' => 'fa-solid fa-handshake',
        'meta' => ['layout' => 'grid', 'columns' => 4],
    ])->assertCreated()
        ->assertJsonPath('data.key', 'partners')
        ->assertJsonPath('data.order', 2)
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.meta', ['layout' => 'grid', 'columns' => 4]);

    $audit = Audit::query()->where('event', AuditEvent::CmsSectionCreated->value)->sole();
    expect($audit->user_id)->toBe($officer->id)
        ->and($audit->auditable_id)->toBe($response->json('data.id'))
        ->and(cmsPage()->content_updated_at)->not->toBeNull();
});

it('rejects a duplicate section key, an unknown type and locked fields', function (): void {
    cmsSection(CmsPageKey::Home, ['key' => 'hero']);
    signIn(contentOfficer());

    $this->postJson(CMS_ADMIN.'/pages/home/sections', ['key' => 'hero', 'type' => 'hero'])
        ->assertUnprocessable()->assertJsonPath('code', 'cms.section_key_taken');
    $this->postJson(CMS_ADMIN.'/pages/home/sections', ['key' => 'banner', 'type' => 'carousel'])
        ->assertUnprocessable()->assertJsonValidationErrors(['type']);
    $this->postJson(CMS_ADMIN.'/pages/home/sections', ['key' => 'banner', 'type' => 'hero', 'order' => 1, 'items' => [['title' => 'x']]])
        ->assertUnprocessable()->assertJsonValidationErrors(['order', 'items']);
    $this->postJson(CMS_ADMIN.'/pages/unknown/sections', ['key' => 'banner', 'type' => 'hero'])->assertNotFound();
});

it('refuses unsafe links, unknown icons, oversized meta and half-empty localized text', function (array $payload, string $field): void {
    $section = cmsSection();
    signIn(contentOfficer());

    $this->patchJson(CMS_ADMIN.'/sections/'.$section->id, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors([$field]);
})->with([
    'javascript link' => [['cta_url' => 'javascript:alert(1)'], 'cta_url'],
    'protocol relative link' => [['cta_url' => '//evil.example.com'], 'cta_url'],
    'plain http link' => [['cta_url' => 'http://example.sa'], 'cta_url'],
    'free icon markup' => [['icon' => '<i class="x"></i>'], 'icon'],
    'meta list' => [['meta' => ['one', 'two']], 'meta'],
    'meta bad key' => [['meta' => ['bad key' => 'x']], 'meta'],
    'meta too deep' => [['meta' => ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 1]]]]]]], 'meta'],
    'dropped demo availability' => [['meta' => ['availability' => 'demo']], 'meta.availability'],
    'unknown availability' => [['meta' => ['availability' => 'soon']], 'meta.availability'],
    'unknown language' => [['title' => ['fr' => 'Titre']], 'title'],
]);

it('accepts only the live and coming-soon availability on sections and items', function (string $availability): void {
    $section = cmsSection();
    $item = cmsItem($section);
    signIn(contentOfficer());

    $this->patchJson(CMS_ADMIN.'/sections/'.$section->id, ['meta' => ['availability' => $availability]])
        ->assertOk()->assertJsonPath('data.meta.availability', $availability);
    $this->putJson(CMS_ADMIN.'/sections/'.$section->id.'/items/'.$item->id, ['meta' => ['availability' => $availability]])
        ->assertOk()->assertJsonPath('data.items.0.meta.availability', $availability);
    $this->putJson(CMS_ADMIN.'/sections/'.$section->id.'/items/'.$item->id, ['meta' => ['availability' => 'demo']])
        ->assertUnprocessable()->assertJsonValidationErrors(['meta.availability']);
})->with(['live', 'coming_soon']);

it('updates only the fields sent and audits the before and after values', function (): void {
    $section = cmsSection(CmsPageKey::Home, ['cta_url' => '/register']);
    signIn(contentOfficer());

    $this->patchJson(CMS_ADMIN.'/sections/'.$section->id, ['title' => ['ar' => 'منصة مؤشر', 'en' => '']])
        ->assertOk()
        ->assertJsonPath('data.title', ['ar' => 'منصة مؤشر', 'en' => ''])
        ->assertJsonPath('data.cta_url', '/register')
        ->assertJsonPath('data.description.ar', 'منصة لقياس نضج الجهات غير الربحية وتميزها المؤسسي.');

    $audit = Audit::query()->where('event', AuditEvent::CmsSectionUpdated->value)->sole();
    expect($audit->old_values['title'])->toBe(['ar' => 'عن منصة مؤشر', 'en' => 'About the Moashir platform'])
        ->and($audit->new_values['title'])->toBe(['ar' => 'منصة مؤشر', 'en' => '']);
});

it('toggles, removes and reorders sections and keeps the order dense', function (): void {
    $first = cmsSection(CmsPageKey::Home, ['key' => 'first', 'sort_order' => 1]);
    $second = cmsSection(CmsPageKey::Home, ['key' => 'second', 'sort_order' => 2]);
    $third = cmsSection(CmsPageKey::Home, ['key' => 'third', 'sort_order' => 3]);
    signIn(contentOfficer());

    $this->patchJson(CMS_ADMIN.'/sections/'.$second->id.'/enabled', ['enabled' => false])
        ->assertOk()->assertJsonPath('data.enabled', false);
    $this->patchJson(CMS_ADMIN.'/sections/reorder', ['ordered_ids' => [$third->id, $first->id, $second->id]])
        ->assertOk()
        ->assertJsonPath('data.0.key', 'third')
        ->assertJsonPath('data.2.key', 'second');
    $this->deleteJson(CMS_ADMIN.'/sections/'.$first->id)->assertNoContent();

    expect(CmsSection::query()->orderBy('sort_order')->pluck('sort_order', 'key')->all())->toBe(['third' => 1, 'second' => 2])
        ->and(CmsSection::withTrashed()->find($first->id)?->trashed())->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::CmsSectionToggled->value)->count())->toBe(1);
});

it('rejects a section reorder that is not a complete permutation of the page', function (): void {
    $first = cmsSection(CmsPageKey::Home, ['key' => 'first']);
    cmsSection(CmsPageKey::Home, ['key' => 'second']);
    $other = cmsSection(CmsPageKey::Lenses, ['key' => 'lens']);
    signIn(contentOfficer());

    $this->patchJson(CMS_ADMIN.'/sections/reorder', ['ordered_ids' => [$first->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'order.incomplete');
    $this->patchJson(CMS_ADMIN.'/sections/reorder', ['ordered_ids' => [$first->id, $other->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'order.incomplete');
    $this->patchJson(CMS_ADMIN.'/sections/reorder', ['ordered_ids' => []])
        ->assertUnprocessable()->assertJsonValidationErrors(['ordered_ids']);
});

it('creates, updates, toggles, reorders and removes items of a section', function (): void {
    $section = cmsSection();
    $existing = cmsItem($section, ['sort_order' => 1]);
    signIn(contentOfficer());

    $created = $this->postJson(CMS_ADMIN.'/sections/'.$section->id.'/items', [
        'title' => ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment'],
        'cta_url' => '#paths',
        'meta' => ['points' => [['ar' => 'مراجعة الشواهد', 'en' => 'Evidence review']]],
    ])->assertCreated()->assertJsonCount(2, 'data.items')->json('data.items.1');

    $this->putJson(CMS_ADMIN.'/sections/'.$section->id.'/items/'.$created['id'], ['description' => ['ar' => 'وصف محدث', 'en' => 'Updated description']])
        ->assertOk()->assertJsonPath('data.items.1.description.en', 'Updated description');
    $this->patchJson(CMS_ADMIN.'/sections/'.$section->id.'/items/'.$existing->id.'/enabled', ['enabled' => false])
        ->assertOk()->assertJsonPath('data.items.0.enabled', false);
    $this->patchJson(CMS_ADMIN.'/sections/'.$section->id.'/items/reorder', ['ordered_ids' => [$created['id'], $existing->id]])
        ->assertOk()->assertJsonPath('data.items.0.id', $created['id']);
    $this->deleteJson(CMS_ADMIN.'/sections/'.$section->id.'/items/'.$created['id'])->assertNoContent();

    expect(CmsSectionItem::query()->where('cms_section_id', $section->id)->pluck('sort_order', 'id')->all())->toBe([$existing->id => 1]);
});

it('never reaches an item through another section', function (): void {
    $section = cmsSection();
    $foreign = cmsItem(cmsSection(CmsPageKey::Lenses));
    signIn(contentOfficer());

    $this->putJson(CMS_ADMIN.'/sections/'.$section->id.'/items/'.$foreign->id, ['title' => ['ar' => 'عنوان']])->assertNotFound();
    $this->deleteJson(CMS_ADMIN.'/sections/'.$section->id.'/items/'.$foreign->id)->assertNotFound();
});

it('updates the page title and SEO text', function (): void {
    signIn(contentOfficer());

    $this->putJson(CMS_ADMIN.'/pages/lenses', [
        'title' => ['ar' => 'العدسات التحليلية', 'en' => 'Analytical lenses'],
        'seo' => ['description' => ['ar' => 'قراءات تحليلية لنتائج الجهات.', 'en' => '']],
    ])->assertOk()
        ->assertJsonPath('data.title.en', 'Analytical lenses')
        ->assertJsonPath('data.seo.description', ['ar' => 'قراءات تحليلية لنتائج الجهات.', 'en' => '']);

    $this->putJson(CMS_ADMIN.'/pages/lenses', ['key' => 'home'])->assertUnprocessable()->assertJsonValidationErrors(['key']);
    expect(Audit::query()->where('event', AuditEvent::CmsPageUpdated->value)->count())->toBe(1);
});

it('lists every code-defined page with its publication state', function (): void {
    signIn(contentOfficer());

    $this->getJson(CMS_ADMIN.'/pages')
        ->assertOk()
        ->assertJsonCount(count(CmsPageKey::cases()), 'data')
        ->assertJsonPath('data.0.key', 'home')
        ->assertJsonPath('data.0.published', false);
});
