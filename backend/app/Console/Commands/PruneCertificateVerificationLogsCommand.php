<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Certificates\PruneCertificateVerificationLogs;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:certificates:prune-verification-logs')]
final class PruneCertificateVerificationLogsCommand extends Command
{
    protected $signature = 'moashir:certificates:prune-verification-logs';

    protected $description = 'Delete public certificate verification log rows older than the retention period.';

    public function handle(PruneCertificateVerificationLogs $prune): int
    {
        $this->components->info(sprintf('Deleted %d certificate verification log row(s).', $prune->handle()));

        return self::SUCCESS;
    }
}
