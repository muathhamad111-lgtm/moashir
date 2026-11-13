<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Contact\PurgeClosedContactMessages;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:contact-messages:purge-closed')]
final class PurgeClosedContactMessagesCommand extends Command
{
    protected $signature = 'moashir:contact-messages:purge-closed';

    protected $description = 'Delete contact messages that were closed longer ago than the retention period.';

    public function handle(PurgeClosedContactMessages $purge): int
    {
        $counts = $purge->handle()->counts;

        $this->components->info(sprintf(
            'Purged %d closed contact message(s) with %d thread entr(ies) older than %d months.',
            ScalarValue::int($counts['purged_messages'] ?? null),
            ScalarValue::int($counts['purged_entries'] ?? null),
            ScalarValue::int($counts['retention_months'] ?? null),
        ));

        return self::SUCCESS;
    }
}
