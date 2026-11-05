<?php

declare(strict_types=1);

use App\Jobs\Finance\ExpirePaymentsJob;
use App\Jobs\Finance\ReconcilePendingPaymentsJob;
use App\Jobs\Platform\RecordQueueHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::command('moashir:expire-invitations')->hourly()->withoutOverlapping();
Schedule::command('moashir:prune-otp-codes')->hourly()->withoutOverlapping();
Schedule::command('auth:clear-resets')->hourly();
Schedule::command('moashir:assessments:sweep-criteria-update-holds')->everyTenMinutes()->withoutOverlapping();
Schedule::command('moashir:assessments:open-paid-requests')->everyTenMinutes()->withoutOverlapping();
Schedule::command('moashir:assessments:flag-overdue')->hourly()->withoutOverlapping();
Schedule::command('moashir:assessments:expire-completion-requests')->everyMinute()->withoutOverlapping();
Schedule::command('moashir:assessments:purge-evidence-bundles')->hourly()->withoutOverlapping();
Schedule::command('moashir:award:advance-cycles')->everyMinute()->withoutOverlapping();
Schedule::command('moashir:award:remind-submission-deadline')->hourly()->withoutOverlapping();
Schedule::command('moashir:award:flag-overdue-committee')->hourly()->withoutOverlapping();
Schedule::job(new ExpirePaymentsJob)->everyMinute();
Schedule::job(new ReconcilePendingPaymentsJob)->everyFiveMinutes();
Schedule::job(new RecordQueueHeartbeat)->everyMinute();
Schedule::command('moashir:files:scan-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('moashir:files:purge-orphans')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('moashir:certificates:prune-verification-logs')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('moashir:contact-messages:purge-closed')->dailyAt('03:45')->withoutOverlapping();
