<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Cms\InstallLandingHomePage;
use App\Data\Cms\LandingInstallOptions;
use App\Exceptions\Console\LandingInstallRefused;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:content:install-landing')]
final class InstallLandingContentCommand extends Command
{
    protected $signature = 'moashir:content:install-landing
        {--editor= : E-mail of the active staff account recorded as the editor (default: the first active Content Officer, else System Administrator)}
        {--force : Replace the home page even when it already holds the landing sections}';

    protected $description = 'Replace the home page sections with the shipped landing content and publish it. Previous sections are soft-deleted for recovery; header and quick links change only while they are empty or still the shipped defaults.';

    public function handle(InstallLandingHomePage $install): int
    {
        $editor = $this->option('editor');

        try {
            $installation = $install->handle(new LandingInstallOptions(is_string($editor) && $editor !== '' ? $editor : null, (bool) $this->option('force')));
        } catch (LandingInstallRefused $refusal) {
            $this->components->error($refusal->getMessage());

            return self::FAILURE;
        }

        if (! $installation->isInstalled) {
            $this->components->info('The home page already holds the landing sections; nothing was changed. Use --force to reinstall.');

            return self::SUCCESS;
        }

        $this->components->info('The landing content was installed and published: '.$installation->summary());

        return self::SUCCESS;
    }
}
