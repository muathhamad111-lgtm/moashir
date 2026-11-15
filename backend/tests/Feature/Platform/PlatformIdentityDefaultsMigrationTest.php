<?php

declare(strict_types=1);

use App\Models\Cms\SiteSettings;
use Illuminate\Support\Facades\DB;

function platformIdentityDefaults(): object
{
    return require database_path('migrations/2026_11_15_150000_fill_default_platform_identity.php');
}

it('fills an empty platform name and description with the default texts', function (): void {
    DB::table('site_settings')->update(['platform_name' => null, 'platform_description' => null]);

    platformIdentityDefaults()->up();

    $settings = SiteSettings::current();
    expect($settings->platform_name?->toArray())->toBe(['ar' => 'مؤشر', 'en' => 'Moashir'])
        ->and($settings->platform_description?->toArray()['ar'])->toStartWith('منصة رقمية لقياس النضج المؤسسي');
});

it('keeps a name the platform already set and removes only the untouched defaults on rollback', function (): void {
    DB::table('site_settings')->update(['platform_name' => json_encode(['ar' => 'منصة مؤشر', 'en' => 'Moashir platform'], JSON_UNESCAPED_UNICODE), 'platform_description' => null]);
    $migration = platformIdentityDefaults();

    $migration->up();
    expect(SiteSettings::current()->platform_name?->toArray())->toBe(['ar' => 'منصة مؤشر', 'en' => 'Moashir platform']);

    $migration->down();
    $settings = SiteSettings::current();
    expect($settings->platform_name?->toArray())->toBe(['ar' => 'منصة مؤشر', 'en' => 'Moashir platform'])
        ->and($settings->platform_description)->toBeNull();
});
