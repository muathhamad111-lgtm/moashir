<?php

declare(strict_types=1);

use App\Enums\Cms\CmsPageKey;
use App\Enums\Identity\RoleKey;
use App\Models\Cms\CmsSection;
use Illuminate\Support\Facades\Notification;

const ACCESSIBILITY_ADMIN = '/api/v1/admin/content/pages/accessibility';

const ACCESSIBILITY_TEXT_AR = "نلتزم بأن تكون منصة مؤشر قابلة للاستخدام لجميع المستفيدين.\n\n- التنقل الكامل بلوحة المفاتيح؛\n- تباين ألوان لا يقل عن المستوى AA.";

beforeEach(function (): void {
    Notification::fake();
});

it('answers 404 while the accessibility page is not published', function (): void {
    cmsSection(CmsPageKey::Accessibility);

    $this->getJson('/api/v1/content/pages/accessibility')->assertNotFound()->assertJsonPath('code', 'cms.page_not_published');
});

it('lets the Content Officer write and publish the accessibility statement as structured text with its dates', function (): void {
    $editor = contentOfficer();

    signIn($editor)->putJson(ACCESSIBILITY_ADMIN, ['title' => ['ar' => 'بيان إمكانية الوصول — منصة مؤشر', 'en' => 'Accessibility Statement — Moashir']])->assertOk();
    $this->postJson(ACCESSIBILITY_ADMIN.'/sections', [
        'key' => 'section-1',
        'type' => 'rich_text',
        'title' => ['ar' => '1. التزامنا', 'en' => '1. Our commitment'],
        'description' => ['ar' => ACCESSIBILITY_TEXT_AR, 'en' => "We are committed to making Moashir usable by everyone.\n\n- full keyboard navigation;\n- AA colour contrast."],
    ])->assertCreated();
    $this->postJson(ACCESSIBILITY_ADMIN.'/publish')->assertOk()->assertJsonPath('data.published', true);

    $this->getJson('/api/v1/content/pages/accessibility')->assertOk()
        ->assertJsonPath('data.key', 'accessibility')
        ->assertJsonPath('data.title.en', 'Accessibility Statement — Moashir')
        ->assertJsonPath('data.published_at', fn (string $value): bool => $value !== '')
        ->assertJsonPath('data.updated_at', fn (string $value): bool => $value !== '')
        ->assertJsonPath('data.sections.0.description.ar', ACCESSIBILITY_TEXT_AR)
        ->assertJsonPath('data.sections.0.blocks.ar', [
            ['type' => 'paragraph', 'text' => 'نلتزم بأن تكون منصة مؤشر قابلة للاستخدام لجميع المستفيدين.'],
            ['type' => 'list', 'ordered' => false, 'start' => null, 'items' => ['التنقل الكامل بلوحة المفاتيح؛', 'تباين ألوان لا يقل عن المستوى AA.']],
        ])
        ->assertJsonPath('data.sections.0.blocks.en.1.items.1', 'AA colour contrast.');
});

it('refuses HTML markup in a content description and stores it without control characters', function (): void {
    $section = cmsSection(CmsPageKey::Accessibility, ['key' => 'section-1']);
    signIn(contentOfficer());

    $this->patchJson('/api/v1/admin/content/sections/'.$section->id, ['description' => ['ar' => 'نص <img src=x onerror=alert(1)>', 'en' => 'Text']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('description.ar');
    $this->patchJson('/api/v1/admin/content/sections/'.$section->id, ['description' => ['ar' => "نص\u{202E} البيان\r\n- بند", 'en' => 'Statement text']])->assertOk();

    expect(CmsSection::query()->findOrFail($section->id)->description?->ar)->toBe("نص البيان\n- بند");
});

it('keeps the accessibility page editable by content managers only', function (RoleKey $role): void {
    cmsSection(CmsPageKey::Accessibility);

    signIn(staffUser($role))->postJson(ACCESSIBILITY_ADMIN.'/publish')->assertForbidden();
    $this->getJson('/api/v1/content/pages/accessibility')->assertNotFound();
})->with([RoleKey::FinanceOfficer, RoleKey::Assessor]);
