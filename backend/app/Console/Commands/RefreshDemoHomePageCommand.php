<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\RefreshDemoHomePage;
use App\Exceptions\Console\DemoDataRefused;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:demo:refresh-home')]
final class RefreshDemoHomePageCommand extends Command
{
    protected $signature = 'moashir:demo:refresh-home';

    protected $description = 'Replace the sections of the demo home page with the current demo content and publish it. Only with APP_DEMO_DATA=true on an installed demo.';

    public function handle(RefreshDemoHomePage $refresh): int
    {
        try {
            $replaced = $refresh->handle();
        } catch (DemoDataRefused $refusal) {
            $this->components->error($refusal->getMessage());

            return self::FAILURE;
        }

        if (! $replaced) {
            $this->components->warn('The demo content has no home page definition; nothing was changed.');

            return self::SUCCESS;
        }

        $this->components->info('The demo home page was replaced with the current demo content and published.');

        return self::SUCCESS;
    }
}
