<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Audit\Audit;
use App\Models\Cms\CmsSection;
use App\Models\Files\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

function uploadContentImage(UploadedFile $file): string
{
    return (string) test()->post('/api/v1/admin/content/images', ['file' => $file], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.file_id');
}

it('stores an uploaded content image as a scanned public asset with a random name', function (): void {
    signIn(contentOfficer());

    $response = $this->post('/api/v1/admin/content/images', ['file' => pngImage('شعار-الجمعية.png')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.mime_type', 'image/png');

    $file = StoredFile::query()->where('public_id', $response->json('data.file_id'))->sole();
    expect($file->purpose)->toBe(StoredFilePurpose::CmsImage)
        ->and($file->visibility)->toBe(FileVisibility::PublicAsset)
        ->and($file->content_class)->toBe('P')
        ->and($file->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($file->path)->not->toContain('شعار')
        ->and($response->json('data.url'))->toBe('/api/v1/content/files/'.$file->public_id);
});

it('refuses a non-image, a disguised file and an infected image', function (): void {
    signIn(contentOfficer());

    $this->post('/api/v1/admin/content/images', ['file' => UploadedFile::fake()->createWithContent('report.pdf', SAMPLE_PDF)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);
    $this->post('/api/v1/admin/content/images', ['file' => UploadedFile::fake()->createWithContent('photo.png', 'not an image at all')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
    $image = pngImage();
    $infected = UploadedFile::fake()->createWithContent('photo.png', $image->getContent().FakeFileScanner::TEST_SIGNATURE);
    $this->post('/api/v1/admin/content/images', ['file' => $infected], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('code', 'file.infected');

    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::CmsImage)->count())->toBe(0);
});

it('attaches an uploaded image to a section and serves it publicly without a session', function (): void {
    $section = cmsSection();
    signIn(contentOfficer());
    $fileId = uploadContentImage(pngImage());

    $this->patchJson('/api/v1/admin/content/sections/'.$section->id, ['image_file_id' => $fileId])
        ->assertOk()
        ->assertJsonPath('data.image.file_id', $fileId);

    $file = StoredFile::query()->where('public_id', $fileId)->sole();
    expect($file->attachable_type)->toBe(new CmsSection()->getMorphClass())
        ->and($file->attachable_id)->toBe($section->id);

    auth()->guard('web')->logout();
    $this->get('/api/v1/content/files/'.$fileId, ['Accept' => 'image/png'])
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'inline; filename=image.png');
});

it('refuses an image attached to other content and an unknown image', function (): void {
    $first = cmsSection(CmsPageKey::Home, ['key' => 'first']);
    $second = cmsSection(CmsPageKey::Home, ['key' => 'second']);
    signIn(contentOfficer());
    $fileId = uploadContentImage(pngImage());
    $this->patchJson('/api/v1/admin/content/sections/'.$first->id, ['image_file_id' => $fileId])->assertOk();

    $this->patchJson('/api/v1/admin/content/sections/'.$second->id, ['image_file_id' => $fileId])
        ->assertUnprocessable()->assertJsonPath('code', 'file.not_usable');
    $this->patchJson('/api/v1/admin/content/sections/'.$second->id, ['image_file_id' => '7a1f5c2e-3b4d-4e5f-8a9b-0c1d2e3f4a5b'])
        ->assertUnprocessable()->assertJsonPath('code', 'file.not_usable');
});

it('never serves private files, unknown files or images that are not clean through the public path', function (): void {
    $evidence = storedTestFile(['malware_scan_status' => MalwareScanStatus::Clean, 'malware_scanned_at' => now()]);
    $pending = storedTestFile(['purpose' => StoredFilePurpose::CmsImage, 'visibility' => FileVisibility::PublicAsset, 'content_class' => 'P']);

    $this->get('/api/v1/content/files/'.$evidence->public_id)->assertNotFound();
    $this->get('/api/v1/content/files/'.$pending->public_id)->assertNotFound();
    $this->get('/api/v1/content/files/2f4c0a1e-9b8d-4c7e-a6f5-3d2c1b0a9e8f')->assertNotFound();
});

it('removes images that are no longer used when the page is published again', function (): void {
    $section = cmsSection();
    $editor = contentOfficer();
    signIn($editor);
    $old = uploadContentImage(pngImage());
    $this->patchJson('/api/v1/admin/content/sections/'.$section->id, ['image_file_id' => $old])->assertOk();
    publishPage(CmsPageKey::Home, $editor);
    $new = uploadContentImage(pngImage());
    $this->patchJson('/api/v1/admin/content/sections/'.$section->id, ['image_file_id' => $new])->assertOk();

    $this->get('/api/v1/content/files/'.$old)->assertOk();

    publishPage(CmsPageKey::Home, $editor);

    $this->get('/api/v1/content/files/'.$old)->assertNotFound();
    $this->get('/api/v1/content/files/'.$new)->assertOk();
    $this->getJson('/api/v1/content/home')->assertJsonPath('data.sections.0.image.file_id', $new);
});

it('reads and partially updates the site settings, audits them and refreshes the public copy', function (): void {
    cmsSection();
    $editor = contentOfficer();
    publishPage(CmsPageKey::Home, $editor);
    $this->getJson('/api/v1/content/home')->assertJsonPath('data.site_settings.social', []);

    $this->patchJson('/api/v1/admin/content/site-settings', [
        'social' => [['platform' => 'x', 'url' => 'https://x.com/moashir.example']],
        'contact' => ['email' => 'info@moashir.example.sa', 'phone' => '+966 11 000 0000', 'address' => ['ar' => 'الرياض', 'en' => '']],
        'quick_links' => [['label' => ['ar' => 'مؤشرات الأداء', 'en' => 'KPIs'], 'url' => '/kpis']],
        'featured_kpi_limit' => 4,
    ])->assertOk()
        ->assertJsonPath('data.contact.address', ['ar' => 'الرياض', 'en' => ''])
        ->assertJsonPath('data.featured_kpi_limit', 4)
        ->assertJsonPath('data.show_featured_kpis', true);

    $this->getJson('/api/v1/content/home')
        ->assertJsonPath('data.site_settings.social.0.platform', 'x')
        ->assertJsonPath('data.site_settings.contact.address', ['ar' => 'الرياض', 'en' => 'الرياض'])
        ->assertJsonPath('data.site_settings.featured_kpi_limit', 4);

    $audit = Audit::query()->where('event', AuditEvent::CmsSiteSettingsUpdated->value)->sole();
    expect(array_keys($audit->new_values))->toBe(['social', 'contact', 'quick_links', 'featured_kpi_limit'])
        ->and($audit->old_values['featured_kpi_limit'])->toBe(6);
});

it('rejects unknown keys and invalid site settings values', function (array $payload, string $field): void {
    signIn(staffUser(RoleKey::SystemAdmin));

    $this->patchJson('/api/v1/admin/content/site-settings', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'unknown key' => [['primary_color' => '#ff0000'], 'primary_color'],
    'http social link' => [['social' => [['platform' => 'x', 'url' => 'http://x.com/a']]], 'social.0.url'],
    'unknown platform' => [['social' => [['platform' => 'myspace', 'url' => 'https://example.sa']]], 'social.0.platform'],
    'unsafe quick link' => [['quick_links' => [['label' => ['ar' => 'رابط'], 'url' => 'javascript:void(0)']]], 'quick_links.0.url'],
    'empty quick link label' => [['quick_links' => [['label' => ['ar' => '', 'en' => ''], 'url' => '/kpis']]], 'quick_links.0.label.ar'],
    'featured limit too high' => [['featured_kpi_limit' => 9], 'featured_kpi_limit'],
    'invalid e-mail' => [['contact' => ['email' => 'not-an-email']], 'contact.email'],
]);
