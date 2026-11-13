<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cms\LegalDraftsInstaller;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:legal:install-drafts')]
final class InstallLegalDraftsCommand extends Command
{
    protected $signature = 'moashir:legal:install-drafts';

    protected $description = 'Store the legal drafts (terms of use, privacy policy, accessibility statement) as UNPUBLISHED drafts for legal review. Idempotent; published versions, existing drafts and an accessibility page with content are never changed.';

    public function handle(LegalDraftsInstaller $installer): int
    {
        $installation = $installer->install();
        $this->components->info(sprintf('Legal drafts: %s. Published versions were not changed.', $installation->summary()));

        return self::SUCCESS;
    }
}
