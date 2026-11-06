<?php

declare(strict_types=1);

it('publishes the certificate level thresholds for the public site', function (): void {
    asGuest()->getJson('/api/v1/content/certificate-levels')->assertOk()
        ->assertJsonPath('data.levels.0', ['level' => 'bronze', 'label' => ['ar' => __('certificates.levels.bronze', [], 'ar'), 'en' => __('certificates.levels.bronze', [], 'en')], 'min_percent' => '60.00'])
        ->assertJsonPath('data.levels.3.level', 'diamond')
        ->assertJsonPath('data.levels.3.min_percent', '90.00')
        ->assertJsonPath('data.participation_below', '60.00');
});

it('follows the thresholds edited in the certificate settings', function (): void {
    setLevelThresholds(['bronze' => '55.00', 'silver' => '65.50', 'gold' => '78.00', 'diamond' => '92.25']);

    asGuest()->getJson('/api/v1/content/certificate-levels')->assertOk()
        ->assertJsonPath('data.levels.1.min_percent', '65.50')
        ->assertJsonPath('data.participation_below', '55.00');
});
