<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Models\Settings\BrandingTheme;
use App\Support\Branding\ContrastRatio;
use Illuminate\Support\Facades\Notification;

const BRANDING = '/api/v1/settings/branding';

const SAFE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"><stop offset="0" stop-color="#3de9ad"/></linearGradient></defs><rect width="10" height="10" fill="url(#g)"/></svg>';

beforeEach(function (): void {
    Notification::fake();
});

it('shows the same theme with its derived readable colours to every signed-in user', function (RoleKey $role): void {
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role))->getJson(BRANDING)
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#98d8d3')
        ->assertJsonPath('data.secondary_color', '#0a2325')
        ->assertJsonPath('data.scheme', [
            'primary' => '#98d8d3',
            'on_primary' => '#100f10',
            'primary_text' => '#547774',
            'secondary' => '#0a2325',
            'on_secondary' => '#ffffff',
        ])
        ->assertJsonPath('data.default_colors', ['primary' => '#98d8d3', 'secondary' => '#0a2325'])
        ->assertJsonPath('data.logo_light', null)
        ->assertJsonMissingPath('data.palette');
})->with([RoleKey::Entity, RoleKey::Assessor, RoleKey::ContentOfficer]);

it('lets the System Administrator choose any primary and secondary colour and audits the change', function (): void {
    $admin = systemAdmin();

    signIn($admin)->putJson(BRANDING, ['primary_color' => '#D4088C', 'secondary_color' => '#4a2a7d'])
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#d4088c')
        ->assertJsonPath('data.secondary_color', '#4a2a7d')
        ->assertJsonPath('data.scheme.on_primary', '#ffffff')
        ->assertJsonPath('data.scheme.primary_text', '#d4088c')
        ->assertJsonPath('data.scheme.on_secondary', '#ffffff');

    $audit = Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->sole();
    expect($audit->old_values)->toMatchArray(['primary_color' => '#98d8d3', 'secondary_color' => '#0a2325'])
        ->and($audit->new_values)->toMatchArray(['primary_color' => '#d4088c', 'secondary_color' => '#4a2a7d'])
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->tags)->toBe('colors');
});

it('derives a darker readable text shade for a light primary colour', function (): void {
    signIn(systemAdmin())->putJson(BRANDING, ['primary_color' => '#ffe066', 'secondary_color' => '#0a2325'])
        ->assertOk()
        ->assertJsonPath('data.scheme.on_primary', '#100f10')
        ->assertJsonPath('data.scheme.primary_text', fn (string $hex): bool => $hex !== '#ffe066'
            && ContrastRatio::between($hex, '#f8f8f8') >= 4.5);
});

it('does not audit a save that keeps the same colours', function (): void {
    signIn(systemAdmin())->putJson(BRANDING, ['primary_color' => '#98D8D3', 'secondary_color' => '#0a2325'])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->count())->toBe(0);
});

it('refuses missing or malformed colours', function (array $payload, string $field): void {
    signIn(systemAdmin())->putJson(BRANDING, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(BrandingTheme::current()->primary_color)->toBe('#98d8d3');
})->with([
    'missing primary' => [['secondary_color' => '#0a2325'], 'primary_color'],
    'named colour' => [['primary_color' => 'purple', 'secondary_color' => '#0a2325'], 'primary_color'],
    'short hex' => [['primary_color' => '#fff', 'secondary_color' => '#0a2325'], 'primary_color'],
    'css injection' => [['primary_color' => '#98d8d3', 'secondary_color' => '#000000;}body{display:none'], 'secondary_color'],
]);

it('refuses a mid-tone colour on which neither light nor dark text reaches AA contrast', function (): void {
    signIn(systemAdmin())->putJson(BRANDING, ['primary_color' => '#777777', 'secondary_color' => '#777777'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'contrast_fail')
        ->assertJsonValidationErrors(['primary_color', 'secondary_color'])
        ->assertJsonPath('meta.pairs.0.pair', 'primary.text_on_fill')
        ->assertJsonPath('meta.pairs.0.minimum', '4.5')
        ->assertJsonPath('meta.pairs.1.pair', 'secondary.text_on_fill')
        ->assertJsonCount(2, 'meta.pairs');

    expect(BrandingTheme::current()->primary_color)->toBe('#98d8d3')
        ->and(Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->count())->toBe(0);
});

it('keeps branding locked to the System Administrator', function (RoleKey $role): void {
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role));

    $this->putJson(BRANDING, ['primary_color' => '#d4088c', 'secondary_color' => '#4a2a7d'])->assertForbidden();
    $this->postJson(BRANDING.'/reset')->assertForbidden();
    $this->post(BRANDING.'/logo', ['file' => pngImage(), 'variant' => 'light'], ['Accept' => 'application/json'])->assertForbidden();

    expect(BrandingTheme::current()->primary_color)->toBe('#98d8d3');
})->with([RoleKey::ContentOfficer, RoleKey::RequestFollower, RoleKey::Entity]);

it('asks guests to sign in before reading or changing the theme', function (): void {
    $this->getJson(BRANDING)->assertUnauthorized();
    $this->putJson(BRANDING, ['primary_color' => '#d4088c', 'secondary_color' => '#4a2a7d'])->assertUnauthorized();
});

it('requires a reason from the Super Admin and records it with the change', function (): void {
    signIn(superAdmin());

    $this->putJson(BRANDING, ['primary_color' => '#1c5f9a', 'secondary_color' => '#0a2325'])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    $this->putJson(BRANDING, ['primary_color' => '#1c5f9a', 'secondary_color' => '#0a2325', 'reason' => 'توحيد لون لوحة التحكم مع هوية الحملة الحالية.'])->assertOk();

    expect(Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->sole()->reason)->toBe('توحيد لون لوحة التحكم مع هوية الحملة الحالية.');
});

it('uploads a light and a dark logo, replaces the previous file and serves them publicly', function (): void {
    signIn(systemAdmin());

    $first = $this->post(BRANDING.'/logo', ['file' => pngImage('logo.png', 400, 120), 'variant' => 'light'], ['Accept' => 'application/json'])
        ->assertOk()->json('data.file_id');
    $second = $this->post(BRANDING.'/logo', ['file' => pngImage('logo.png', 400, 120), 'variant' => 'light'], ['Accept' => 'application/json'])
        ->assertOk()->json('data.file_id');
    $this->post(BRANDING.'/logo', ['file' => pngImage('logo-dark.png', 400, 120), 'variant' => 'dark'], ['Accept' => 'application/json'])->assertOk();

    $this->getJson(BRANDING)
        ->assertJsonPath('data.logo_light.file_id', $second)
        ->assertJsonPath('data.logo_dark.url', fn (string $url): bool => str_starts_with($url, '/api/v1/content/files/'));
    $this->get('/api/v1/content/files/'.$first)->assertNotFound();
    $this->get('/api/v1/content/files/'.$second)->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(StoredFile::query()->ofPurpose(StoredFilePurpose::BrandingLogo)->count())->toBe(2);
});

it('stores the favicon and the public site logo and shows them on the public site, refreshed after each change', function (): void {
    cmsSection();
    publishPage(CmsPageKey::Home, systemAdmin());
    $this->getJson('/api/v1/content/home')->assertJsonPath('data.site_settings.branding', ['site_logo' => null, 'favicon' => null]);

    $favicon = $this->post(BRANDING.'/logo', ['file' => pngImage('favicon.png', 300, 200), 'variant' => 'favicon'], ['Accept' => 'application/json'])
        ->assertOk()->json('data.file_id');
    $siteLogo = $this->post(BRANDING.'/logo', ['file' => pngImage('site.png', 480, 160), 'variant' => 'site'], ['Accept' => 'application/json'])
        ->assertOk()->json('data.file_id');

    $this->getJson(BRANDING)
        ->assertJsonPath('data.favicon.file_id', $favicon)
        ->assertJsonPath('data.logo_site.file_id', $siteLogo);
    $this->getJson('/api/v1/content/home')
        ->assertJsonPath('data.site_settings.branding.favicon.file_id', $favicon)
        ->assertJsonPath('data.site_settings.branding.site_logo.file_id', $siteLogo);

    $this->postJson(BRANDING.'/reset')->assertOk()->assertJsonPath('data.favicon', null)->assertJsonPath('data.logo_site', null);
    $this->getJson('/api/v1/content/home')->assertJsonPath('data.site_settings.branding', ['site_logo' => null, 'favicon' => null]);
    $this->get('/api/v1/content/files/'.$favicon)->assertNotFound();
});

it('refuses every SVG logo, even a plain one, as an unsupported type', function (string $markup): void {
    signIn(systemAdmin());

    $this->post(BRANDING.'/logo', ['file' => svgFile($markup), 'variant' => 'light'], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('file');
})->with([
    'plain' => [SAFE_SVG],
    'script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'entity-encoded handler' => ['<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="href" values="&#106;avascript:alert(1)"/></svg>'],
]);

it('refuses SVG markup disguised as a PNG and an oversized PNG', function (string $file): void {
    signIn(systemAdmin());
    $upload = match ($file) {
        'disguised' => svgFile(SAFE_SVG, 'logo.png'),
        default => pngImage('wide.png', 5000, 10),
    };

    $this->post(BRANDING.'/logo', ['file' => $upload, 'variant' => 'light'], ['Accept' => 'application/json'])
        ->assertUnprocessable();
})->with(['disguised', 'oversized']);

it('re-encodes the PNG logo so no original bytes are kept or served', function (): void {
    signIn(systemAdmin());
    $tagged = logoUpload(pngWithTextChunk(logoImageBytes('png', 400, 120), 'device-location'), 'logo.png');

    $fileId = $this->post(BRANDING.'/logo', ['file' => $tagged, 'variant' => 'light'], ['Accept' => 'application/json'])->assertOk()->json('data.file_id');

    $served = $this->get('/api/v1/content/files/'.$fileId)->assertOk()->assertHeader('Content-Type', 'image/png')->streamedContent();

    expect($served)->not->toContain('device-location')
        ->and(getimagesizefromstring($served))->toMatchArray([0 => 400, 1 => 120])
        ->and(StoredFile::query()->ofPurpose(StoredFilePurpose::BrandingLogo)->count())->toBe(1);
});

it('rejects an unknown logo variant', function (): void {
    signIn(systemAdmin())->post(BRANDING.'/logo', ['file' => pngImage(), 'variant' => 'sidebar'], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['variant']);
});

it('resets the colours and the logos to the brand defaults and audits the reset', function (): void {
    signIn(systemAdmin());
    $this->putJson(BRANDING, ['primary_color' => '#a63d1f', 'secondary_color' => '#4a2a7d'])->assertOk();
    $logo = $this->post(BRANDING.'/logo', ['file' => pngImage(), 'variant' => 'dark'], ['Accept' => 'application/json'])->json('data.file_id');

    $this->postJson(BRANDING.'/reset')
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#98d8d3')
        ->assertJsonPath('data.secondary_color', '#0a2325')
        ->assertJsonPath('data.logo_dark', null);

    $this->get('/api/v1/content/files/'.$logo)->assertNotFound();
    expect(Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->where('tags', 'reset')->sole()->old_values['primary_color'])->toBe('#a63d1f');
});
