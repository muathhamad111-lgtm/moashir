<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\RotateDemoPasswords;
use App\Exceptions\Console\DemoDataRefused;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:demo:rotate-passwords')]
final class RotateDemoPasswordsCommand extends Command
{
    protected $signature = 'moashir:demo:rotate-passwords
        {--accounts-file= : Absolute path of a new file outside the application that receives the new demo passwords (mode 600)}';

    protected $description = 'Issue new random passwords for every demo account and end their sessions. Only with APP_DEMO_DATA=true.';

    public function handle(RotateDemoPasswords $rotate): int
    {
        try {
            $accounts = $rotate->handle((string) $this->option('accounts-file'));
        } catch (DemoDataRefused $refusal) {
            $this->components->error($refusal->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('New passwords issued for %d demo accounts and written only to the accounts file given with --accounts-file.', count($accounts)));

        return self::SUCCESS;
    }
}
