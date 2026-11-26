<?php

declare(strict_types=1);

namespace App\Integrations\Documents;

final class ChromeLaunchOptions
{
    private const array HARDENING_FLAGS = [
        '--disable-extensions',
        '--disable-component-update',
        '--disable-default-apps',
        '--disable-domain-reliability',
        '--disable-dev-shm-usage',
        '--disable-features=Translate,MediaRouter,OptimizationHints,AutofillServerCommunication',
        '--no-default-browser-check',
        '--no-pings',
        '--host-resolver-rules=MAP * ~NOTFOUND',
        '--remote-allow-origins=http://127.0.0.1',
    ];

    private const array REMOVED_SWITCHES = ['--remote-allow-origins=*'];

    private const array WINDOW_SIZE = [1240, 1754];

    /**
     * @return array<string, mixed>
     */
    public function for(string $profileDirectory, int $timeoutSeconds, int $startupSeconds): array
    {
        return [
            'headless' => true,
            'noSandbox' => false,
            'keepAlive' => false,
            'enableImages' => true,
            'disableNotifications' => true,
            'windowSize' => self::WINDOW_SIZE,
            'userDataDir' => $profileDirectory,
            'startupTimeout' => $startupSeconds,
            'sendSyncDefaultTimeout' => $timeoutSeconds * 1000,
            'customFlags' => self::HARDENING_FLAGS,
            'excludedSwitches' => self::REMOVED_SWITCHES,
            'envVariables' => ['HOME' => $profileDirectory, 'TMPDIR' => $profileDirectory],
        ];
    }
}
