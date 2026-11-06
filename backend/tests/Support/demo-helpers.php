<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

function demoAccountsDirectory(): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'moashir-demo-'.bin2hex(random_bytes(6));
    mkdir($directory, 0700);

    test()->beforeApplicationDestroyed(static function () use ($directory): void {
        array_map(unlink(...), glob($directory.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($directory);
    });

    return $directory;
}

function enableDemoData(): void
{
    config()->set('moashir.demo.enabled', true);
}

function installDemoPrerequisites(): void
{
    installedSeedCriteria();
    test()->artisan('moashir:regions:install-seed')->assertSuccessful();
}

/**
 * @return array{exit: int, output: string}
 */
function runDemoCommand(string $command, string $accountsFile): array
{
    $exit = Artisan::call($command, ['--accounts-file' => $accountsFile]);

    return ['exit' => $exit, 'output' => Artisan::output()];
}

/**
 * @return array<string, string>
 */
function demoPasswordsIn(string $accountsFile): array
{
    preg_match_all('/^email:\s+(\S+)\R^password:\s+(\S+)$/m', (string) file_get_contents($accountsFile), $matches);

    return array_combine($matches[1], $matches[2]);
}

function expectRefusalWasLogged(string $reason): void
{
    Log::shouldHaveReceived('critical')
        ->withArgs(static fn (string $message, array $context): bool => $message === 'platform.demo_data_refused' && $context['reason'] === $reason)
        ->once();
}
