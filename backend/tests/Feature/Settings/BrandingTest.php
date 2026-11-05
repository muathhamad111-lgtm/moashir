<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Files\StoredFilePurpose;
use App\Enums\Identity\RoleKey;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use App\Models\Settings\BrandingTheme;
use Illuminate\Support\Facades\Notification;

const BRANDING = '/api/v1/settings/branding';

const SAFE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"><stop offset="0" stop-color="#3de9ad"/></linearGradient></defs><rect width="10" height="10" fill="url(#g)"/></svg>';

beforeEach(function (): void {
    Notification::fake();
});

it('shows the same theme to every signed-in user without the palette', function (RoleKey $role): void {
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role))->getJson(BRANDING)
        ->assertOk()
        ->assertJsonPath('data.accent_token', 'brand_default')
        ->assertJsonPath('data.logo_light', null)
        ->assertJsonPath('data.palette', []);
})->with([RoleKey::Entity, RoleKey::Assessor, RoleKey::ContentOfficer]);

it('lists the controlled palette to the System Administrator', function (): void {
    signIn(systemAdmin())->getJson(BRANDING)
        ->assertOk()
        ->assertJsonCount(9, 'data.palette')
        ->assertJsonPath('data.palette.0', [
            'token' => 'brand_default',
            'hex' => '#98d8d3',
            'text_on_light_hex' => '#1d7470',
            'label' => ['ar' => 'ألوان الهوية الافتراضية', 'en' => 'Brand default'],
        ]);
});

it('lets the System Administrator pick an accent from the palette and audits the change', function (): void {
    $admin = systemAdmin();

    signIn($admin)->putJson(BRANDING, ['accent_token' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.accent_token', 'teal');

    $audit = Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->sole();
    expect($audit->old_values['accent_token'])->toBe('brand_default')
        ->and($audit->new_values['accent_token'])->toBe('teal')
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->tags)->toBe('accent');
});

it('refuses a token outside the controlled palette and a free hex value', function (string $token): void {
    signIn(systemAdmin())->putJson(BRANDING, ['accent_token' => $token])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'token.unknown');

    expect(BrandingTheme::current()->accent_token->value)->toBe('brand_default');
})->with(['purple', '#ff00ff']);

it('refuses a palette colour that fails the AA contrast check in light or dark mode', function (): void {
    config()->set('moashir.branding.palette.amber', ['fill' => '#1a2b2c', 'text_on_light' => '#e9f0f0']);

    signIn(systemAdmin())->putJson(BRANDING, ['accent_token' => 'amber'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'contrast_fail')
        ->assertJsonPath('meta.pairs.0.pair', 'light.accent_text_on_content')
        ->assertJsonPath('meta.pairs.0.minimum', '4.5')
        ->assertJsonCount(4, 'meta.pairs');

    expect(BrandingTheme::current()->accent_token->value)->toBe('brand_default')
        ->and(Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->count())->toBe(0);
});

it('keeps branding locked to the System Administrator', function (RoleKey $role): void {
    signIn($role === RoleKey::Entity ? entityUser() : staffUser($role));

    $this->putJson(BRANDING, ['accent_token' => 'teal'])->assertForbidden();
    $this->postJson(BRANDING.'/reset')->assertForbidden();
    $this->post(BRANDING.'/logo', ['file' => pngImage(), 'variant' => 'light'], ['Accept' => 'application/json'])->assertForbidden();
})->with([RoleKey::ContentOfficer, RoleKey::RequestFollower, RoleKey::Entity]);

it('asks guests to sign in before reading the theme', function (): void {
    $this->getJson(BRANDING)->assertUnauthorized();
});

it('requires a reason from the Super Admin and records it with the change', function (): void {
    signIn(superAdmin());

    $this->putJson(BRANDING, ['accent_token' => 'sky'])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    $this->putJson(BRANDING, ['accent_token' => 'sky', 'reason' => 'توحيد لون لوحة التحكم مع هوية الحملة الحالية.'])->assertOk();

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

it('resets the accent and both logos to the brand defaults and audits the reset', function (): void {
    signIn(systemAdmin());
    $this->putJson(BRANDING, ['accent_token' => 'coral'])->assertOk();
    $logo = $this->post(BRANDING.'/logo', ['file' => pngImage(), 'variant' => 'dark'], ['Accept' => 'application/json'])->json('data.file_id');

    $this->postJson(BRANDING.'/reset')
        ->assertOk()
        ->assertJsonPath('data.accent_token', 'brand_default')
        ->assertJsonPath('data.logo_dark', null);

    $this->get('/api/v1/content/files/'.$logo)->assertNotFound();
    expect(Audit::query()->where('event', AuditEvent::BrandingUpdated->value)->where('tags', 'reset')->sole()->old_values['accent_token'])->toBe('coral');
});
