<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\InstallDemoContent;
use App\Actions\Demo\InstallDemoData;
use App\Exceptions\Console\DemoDataRefused;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:demo:install')]
final class InstallDemoDataCommand extends Command
{
    protected $signature = 'moashir:demo:install
        {--accounts-file= : Absolute path of a new file outside the application that receives the demo account passwords (mode 600)}
        {--content-only : Add only the demo public content (homepage, intro pages, KPIs) to an already installed demo; existing content is kept}';

    protected $description = 'Install fictional demo data (staff per role, entities, a draft award cycle, TEST-mode payments, public content and KPIs). Only with APP_DEMO_DATA=true and when no real entity exists; never creates the Super Admin.';

    public function handle(InstallDemoData $install, InstallDemoContent $installContent): int
    {
        try {
            if ($this->option('content-only') === true) {
                $content = $installContent->handle();
                $this->components->info(sprintf('Demo content installed: %s.', $content->summary()));

                return self::SUCCESS;
            }

            $installation = $install->handle((string) $this->option('accounts-file'));
        } catch (DemoDataRefused $refusal) {
            $this->components->error($refusal->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Demo data installed: %s.', $installation->summary()));
        $this->components->info('The demo account passwords were written only to the accounts file given with --accounts-file.');

        return self::SUCCESS;
    }
}
