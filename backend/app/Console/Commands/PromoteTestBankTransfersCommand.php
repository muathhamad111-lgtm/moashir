<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Finance\PromoteTestBankTransfers;
use App\Services\Finance\TestBankTransferCandidates;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:finance:promote-test-transfers')]
final class PromoteTestBankTransfersCommand extends Command
{
    private const int REASON_MIN = 10;

    protected $signature = 'moashir:finance:promote-test-transfers
        {--reason= : Why the transfers are promoted (recorded in the audit log, at least 10 characters)}
        {--dry-run : List the transfers that would be promoted without changing anything}';

    protected $description = 'Make the bank transfers that entities submitted in test mode official: live mode and an official re-issue of their test certificates. Demo entities are left as they are.';

    public function handle(TestBankTransferCandidates $candidates, PromoteTestBankTransfers $promote): int
    {
        if ($this->option('dry-run') === true) {
            $references = array_values($candidates->query()->orderBy('id')->pluck('reference')->map(static fn (mixed $reference): string => ScalarValue::string($reference))->all());
            $this->components->info(sprintf('%d bank transfers would be promoted.', count($references)));
            $this->components->bulletList($references);

            return self::SUCCESS;
        }

        $reason = trim(ScalarValue::string($this->option('reason')));

        if (mb_strlen($reason) < self::REASON_MIN) {
            $this->components->error('Give a --reason of at least 10 characters; it is recorded in the audit log.');

            return self::INVALID;
        }

        $promoted = $promote->handle($reason);
        $this->components->info(sprintf('Promoted %d bank transfers.', count($promoted)));
        $this->components->bulletList($promoted);

        return self::SUCCESS;
    }
}
