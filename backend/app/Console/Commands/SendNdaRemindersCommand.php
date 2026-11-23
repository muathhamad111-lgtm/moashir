<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Nda\SendScheduledNdaReminder;
use Illuminate\Console\Command;

final class SendNdaRemindersCommand extends Command
{
    protected $signature = 'moashir:send-nda-reminders';

    protected $description = 'Send the non-disclosure agreement reminder (NDA-02) once the reminder delay of the current version has passed.';

    public function handle(SendScheduledNdaReminder $reminder): int
    {
        $this->components->info($reminder->handle() ? 'Reminder round queued.' : 'No reminder due.');

        return self::SUCCESS;
    }
}
