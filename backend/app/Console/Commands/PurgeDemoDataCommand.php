<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\PreviewDemoPurge;
use App\Actions\Demo\PurgeDemoData;
use App\Exceptions\Console\DemoDataRefused;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:demo:purge')]
final class PurgeDemoDataCommand extends Command
{
    protected $signature = 'moashir:demo:purge
        {--force : Remove the records; without it the command only prints what would be removed}';

    protected $description = 'Remove every record of the demo-records register with its dependants and files. Only in the demo posture (MOASHIR_DEMO=true) and with the database owner role; a dry-run summary by default.';

    public function handle(PreviewDemoPurge $preview, PurgeDemoData $purge): int
    {
        try {
            if ($this->option('force') !== true) {
                return $this->printPreview($preview->handle());
            }

            $counts = $purge->handle()->toArray();
        } catch (DemoDataRefused $refusal) {
            $this->components->error($refusal->getMessage());

            return self::FAILURE;
        }

        $this->printCounts($counts);
        $this->components->info(array_sum($counts) === 0 ? 'Nothing to purge: the demo-records register is empty.' : 'Demo data purged.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function printPreview(array $counts): int
    {
        $this->printCounts($counts);
        $this->components->warn('Dry run: nothing was removed. Run again with --force to purge.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function printCounts(array $counts): void
    {
        foreach ($counts as $label => $count) {
            $this->components->twoColumnDetail($label, (string) $count);
        }
    }
}
