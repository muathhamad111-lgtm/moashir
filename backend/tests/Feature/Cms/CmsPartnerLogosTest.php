<?php

declare(strict_types=1);

use App\Enums\Cms\CmsPageKey;
use App\Enums\Cms\CmsSectionType;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Cms\CmsSection;
use App\Models\Files\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

const PARTNER_SVG = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40"><defs><linearGradient id="g"><stop offset="0" stop-color="#0d6b6b"/></linearGradient></defs><rect width="120" height="40" fill="url(#g)"/><text x="4" y="28">شريك</text></svg>';

const PARTNER_NAME = ['ar' => 'وزارة الصحة', 'en' => 'Ministry of Health'];

beforeEach(function (): void {
    Notification::fake();
});

function partnersSection(): CmsSection
{
    return CmsSection::factory()->onPage(CmsPageKey::Home)->ofType(CmsSectionType::Partners)->create(['key' => 'partners']);
}

function uploadPartnerLogo(UploadedFile $file): string
{
    return (string) test()->post('/api/v1/admin/content/logos', ['file' => $file], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.file_id');
}

it('stores PNG, JPG and safe SVG partner logos as scanned public assets', function (UploadedFile $file, string $mimeType): void {
    signIn(contentOfficer());

    $fileId = uploadPartnerLogo($file);

    $stored = StoredFile::query()->where('public_id', $fileId)->sole();
    expect($stored->purpose)->toBe(StoredFilePurpose::CmsLogo)
        ->and($stored->mime_type)->toBe($mimeType)
        ->and($stored->visibility)->toBe(FileVisibility::PublicAsset)
        ->and($stored->malware_scan_status)->toBe(MalwareScanStatus::Clean);
})->with([
    'png' => [fn (): UploadedFile => pngImage('logo.png'), 'image/png'],
    'jpg' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.jpg', 240, 100), 'image/jpeg'],
    'svg' => [fn (): UploadedFile => svgFile(PARTNER_SVG), 'image/svg+xml'],
]);

it('rejects an SVG logo that could run script or reach outside the file', function (string $markup): void {
    signIn(contentOfficer());

    $this->post('/api/v1/admin/content/logos', ['file' => svgFile($markup)], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.unsafe_svg')
        ->assertJsonValidationErrors('file');

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::CmsLogo)->count())->toBe(0);
})->with([
    'script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'event handler' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>'],
    'foreign object' => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>'],
    'external href' => ['<svg xmlns="http://www.w3.org/2000/svg"><use href="https://example.org/a.svg#x"/></svg>'],
    'xlink to http' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="http://example.org/a.png"/></svg>'],
    'entity-encoded script url' => ['<svg xmlns="http://www.w3.org/2000/svg"><a href="&#106;avascript:alert(1)"><rect width="1" height="1"/></a></svg>'],
    'doctype entity' => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>'],
    'style import' => ['<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://example.org/a.css);</style></svg>'],
    'external url in style' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:url(https://example.org/p.svg#p)" width="1" height="1"/></svg>'],
    'stylesheet instruction' => ['<?xml-stylesheet href="https://example.org/a.css"?><svg xmlns="http://www.w3.org/2000/svg"/>'],
    'not svg' => ['<html xmlns="http://www.w3.org/1999/xhtml"><body/></html>'],
]);

it('refuses a logo of the wrong type, a disguised file, an infected file and one over 1 MB', function (): void {
    signIn(contentOfficer());
    $infected = UploadedFile::fake()->createWithContent('logo.png', pngImage()->getContent().FakeFileScanner::TEST_SIGNATURE);

    $this->post('/api/v1/admin/content/logos', ['file' => UploadedFile::fake()->createWithContent('logo.pdf', SAMPLE_PDF)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);
    $this->post('/api/v1/admin/content/logos', ['file' => svgFile(PARTNER_SVG, 'logo.png')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
    $this->post('/api/v1/admin/content/logos', ['file' => $infected], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('code', 'file.infected');
    $this->post('/api/v1/admin/content/logos', ['file' => UploadedFile::fake()->image('big.png')->size(1100)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::CmsLogo)->count())->toBe(0);
});

it('keeps content images to PNG and JPG', function (): void {
    signIn(contentOfficer());

    $this->post('/api/v1/admin/content/images', ['file' => svgFile(PARTNER_SVG)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

it('denies logo uploads to signed-out visitors and to roles without content management', function (): void {
    $this->post('/api/v1/admin/content/logos', ['file' => pngImage()], ['Accept' => 'application/json'])->assertUnauthorized();

    signIn(financeOfficer());
    $this->post('/api/v1/admin/content/logos', ['file' => pngImage()], ['Accept' => 'application/json'])->assertForbidden();
});

it('adds a partner with a name in both languages, a logo and an https link, and publishes it', function (): void {
    $section = partnersSection();
    $editor = contentOfficer();
    signIn($editor);
    $logo = uploadPartnerLogo(svgFile(PARTNER_SVG));

    $this->postJson('/api/v1/admin/content/sections/'.$section->id.'/items', [
        'title' => PARTNER_NAME,
        'image_file_id' => $logo,
        'cta_url' => 'https://www.moh.gov.sa',
    ])->assertCreated();

    publishPage(CmsPageKey::Home, $editor);
    auth()->guard('web')->logout();

    $partner = collect($this->getJson('/api/v1/content/home')->json('data.sections'))->firstWhere('type', 'partners')['items'][0];
    expect($partner['title'])->toBe(PARTNER_NAME)
        ->and($partner['cta_url'])->toBe('https://www.moh.gov.sa')
        ->and($partner['image']['file_id'])->toBe($logo);

    $this->get('/api/v1/content/files/'.$logo)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox")
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('requires both names and a logo for a new partner', function (array $payload, array $errors): void {
    $section = partnersSection();
    signIn(contentOfficer());

    $this->postJson('/api/v1/admin/content/sections/'.$section->id.'/items', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'nothing' => [[], ['title', 'image_file_id']],
    'arabic name only' => [['title' => ['ar' => 'وزارة الصحة', 'en' => '']], ['title.en', 'image_file_id']],
]);

it('accepts only an https link for a partner', function (string $link): void {
    $section = partnersSection();
    $item = cmsItem($section, ['title' => PARTNER_NAME, 'description' => null, 'icon' => null]);
    signIn(contentOfficer());

    $this->putJson('/api/v1/admin/content/sections/'.$section->id.'/items/'.$item->id, ['cta_url' => $link])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cta_url']);
})->with(['http://www.moh.gov.sa', 'javascript:alert(1)', '/kpis', 'mailto:info@moh.gov.sa', '#partners']);

it('refuses the generic content fields and a removed logo on a partner', function (string $field, mixed $value): void {
    $section = partnersSection();
    $item = cmsItem($section, ['title' => PARTNER_NAME, 'description' => null, 'icon' => null]);
    signIn(contentOfficer());

    $this->putJson('/api/v1/admin/content/sections/'.$section->id.'/items/'.$item->id, [$field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'subtitle' => ['subtitle', ['ar' => 'نص', 'en' => 'Text']],
    'description' => ['description', ['ar' => 'نص', 'en' => 'Text']],
    'button text' => ['cta_label', ['ar' => 'زيارة', 'en' => 'Visit']],
    'icon' => ['icon', 'fa-solid fa-star'],
    'display options' => ['meta', ['eyebrow' => ['ar' => 'شريك', 'en' => 'Partner']]],
    'removed logo' => ['image_file_id', null],
]);

it('takes a content image as a partner logo only through the logo upload', function (): void {
    $section = partnersSection();
    signIn(contentOfficer());
    $image = (string) $this->post('/api/v1/admin/content/images', ['file' => pngImage()], ['Accept' => 'application/json'])->json('data.file_id');

    $this->postJson('/api/v1/admin/content/sections/'.$section->id.'/items', ['title' => PARTNER_NAME, 'image_file_id' => $image])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.not_usable');
});

it('keeps the generic item form for every other section type', function (): void {
    $section = cmsSection(CmsPageKey::Home, ['type' => CmsSectionType::Paths, 'key' => 'paths-extra']);
    signIn(contentOfficer());

    $this->postJson('/api/v1/admin/content/sections/'.$section->id.'/items', [
        'title' => ['ar' => 'مسار', 'en' => ''],
        'subtitle' => ['ar' => 'وصف', 'en' => 'Line'],
        'icon' => 'fa-solid fa-star',
        'cta_url' => '/kpis',
    ])->assertCreated();
});

it('keeps referenced partner logos and removes replaced ones when the page is published', function (): void {
    $section = partnersSection();
    $editor = contentOfficer();
    signIn($editor);
    $old = uploadPartnerLogo(pngImage());
    $this->postJson('/api/v1/admin/content/sections/'.$section->id.'/items', ['title' => PARTNER_NAME, 'image_file_id' => $old])->assertCreated();
    $itemId = (int) $section->items()->value('id');
    publishPage(CmsPageKey::Home, $editor);

    $new = uploadPartnerLogo(svgFile(PARTNER_SVG));
    signIn($editor)->putJson('/api/v1/admin/content/sections/'.$section->id.'/items/'.$itemId, ['image_file_id' => $new])->assertOk();
    publishPage(CmsPageKey::Home, $editor);

    $this->get('/api/v1/content/files/'.$old)->assertNotFound();
    $this->get('/api/v1/content/files/'.$new)->assertOk();
});

it('clears the unused fields on existing partners and keeps their names, links and logos', function (): void {
    $section = partnersSection();
    $logo = storedTestFile(['purpose' => StoredFilePurpose::CmsImage, 'mime_type' => 'image/png', 'visibility' => FileVisibility::PublicAsset, 'content_class' => 'P', 'malware_scan_status' => MalwareScanStatus::Clean, 'malware_scanned_at' => now()]);
    $partner = cmsItem($section, ['title' => PARTNER_NAME, 'cta_url' => 'https://www.moh.gov.sa', 'subtitle' => ['ar' => 'س', 'en' => 's'], 'meta' => ['eyebrow' => ['ar' => 'ش', 'en' => 'p']]]);
    $partner->forceFill(['image_file_id' => $logo->id])->save();
    $other = cmsItem(cmsSection(CmsPageKey::Home, ['key' => 'other-paths']));

    (require database_path('migrations/2026_11_22_160100_clear_unused_fields_on_partner_items.php'))->up();

    $partner->refresh();
    expect($partner->title?->toArray())->toBe(PARTNER_NAME)
        ->and($partner->cta_url)->toBe('https://www.moh.gov.sa')
        ->and($partner->image_file_id)->toBe($logo->id)
        ->and($partner->subtitle)->toBeNull()
        ->and($partner->description)->toBeNull()
        ->and($partner->icon)->toBeNull()
        ->and($partner->meta)->toBeNull()
        ->and($other->refresh()->icon)->toBe('fa-solid fa-list-check');
});
