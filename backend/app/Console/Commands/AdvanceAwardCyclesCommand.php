<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Award\AdvanceCycleSchedule;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:award:advance-cycles')]
final class AdvanceAwardCyclesCommand extends Command
{
    protected $signature = 'moashir:award:advance-cycles';

    protected $description = 'Open the award cycle whose submission start has come, close the submissions of the cycle whose submission end has passed and start the committee assessment on its start date.';

    public function handle(AdvanceCycleSchedule $action): int
    {
        $result = $action->handle();
        $this->components->info(sprintf(
            'Opened %d, closed the submissions of %d and started the committee assessment of %d award cycles.',
            $result['opened'],
            $result['closed'],
            $result['committees_started'],
        ));

        return self::SUCCESS;
    }
}
