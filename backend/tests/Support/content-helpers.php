<?php

declare(strict_types=1);

use App\Enums\Cms\CmsPageKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use Illuminate\Http\UploadedFile;

function contentOfficer(): User
{
    return staffUser(RoleKey::ContentOfficer);
}

function cmsPage(CmsPageKey $key = CmsPageKey::Home): CmsPage
{
    return CmsPage::query()->where('key', $key)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmsSection(CmsPageKey $key = CmsPageKey::Home, array $attributes = []): CmsSection
{
    return CmsSection::factory()->onPage($key)->create($attributes);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function cmsItem(CmsSection $section, array $attributes = []): CmsSectionItem
{
    return CmsSectionItem::factory()->create(['cms_section_id' => $section->id, ...$attributes]);
}

function publishPage(CmsPageKey $key, User $editor): void
{
    signIn($editor)->postJson('/api/v1/admin/content/pages/'.$key->value.'/publish')->assertOk();
}

function pngImage(string $name = 'image.png', int $width = 120, int $height = 60): UploadedFile
{
    return UploadedFile::fake()->image($name, $width, $height);
}

function svgFile(string $markup, string $name = 'logo.svg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $markup);
}

const LEGAL_BODY_AR = "مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]\n\n## 1. مقدمة\n\nتوضح هذه السياسة كيف تجمع منصة مؤشر البيانات الشخصية.\n\n- زوار المنصة؛\n- الجهات المسجلة.\n\n| فئة البيانات | مدة الاحتفاظ |\n|---|---|\n| رسائل «تواصل معنا» | 24 شهراً |";

const LEGAL_BODY_EN = "Draft for legal review — last updated: [[approval date]]\n\n## 1. Introduction\n\nThis policy explains how Moashir collects personal data.\n\n- visitors of the platform;\n- registered entities.\n\n| Data category | Retention period |\n|---|---|\n| \"Contact us\" messages | 24 months |";

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legalPayload(array $overrides = []): array
{
    return [
        'version' => '2026-12',
        'title' => ['ar' => 'سياسة الخصوصية — منصة مؤشر', 'en' => 'Privacy Policy — Moashir'],
        'body' => ['ar' => LEGAL_BODY_AR, 'en' => LEGAL_BODY_EN],
        ...$overrides,
    ];
}

function legalDraft(TermsKind $kind = TermsKind::Privacy, string $version = '2026-12'): TermsVersion
{
    $payload = legalPayload(['version' => $version]);

    return TermsVersion::query()->forceCreate([
        'kind' => $kind->value,
        'version' => $version,
        'title' => $payload['title'],
        'body' => $payload['body'],
        'is_current' => false,
    ]);
}
