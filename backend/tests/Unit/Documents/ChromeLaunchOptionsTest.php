<?php

declare(strict_types=1);

use App\Integrations\Documents\ChromeLaunchOptions;

it('never turns the Chrome sandbox off and keeps the browser offline and private', function (): void {
    $options = new ChromeLaunchOptions()->for('/tmp/moashir-pdf-profile', 60, 20);
    $flags = $options['customFlags'];

    expect($options['noSandbox'])->toBeFalse()
        ->and($options['headless'])->toBeTrue()
        ->and($options['userDataDir'])->toBe('/tmp/moashir-pdf-profile')
        ->and($options['envVariables'])->toBe(['HOME' => '/tmp/moashir-pdf-profile', 'TMPDIR' => '/tmp/moashir-pdf-profile'])
        ->and($options['sendSyncDefaultTimeout'])->toBe(60000)
        ->and($options['startupTimeout'])->toBe(20)
        ->and($flags)->toContain('--host-resolver-rules=MAP * ~NOTFOUND')
        ->and($flags)->toContain('--remote-allow-origins=http://127.0.0.1')
        ->and($flags)->not->toContain('--no-sandbox')
        ->and($options['excludedSwitches'])->toBe(['--remote-allow-origins=*']);
});
