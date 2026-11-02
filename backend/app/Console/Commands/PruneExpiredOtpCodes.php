<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Identity\OtpCode;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:prune-otp-codes')]
final class PruneExpiredOtpCodes extends Command
{
    private const int RETENTION_HOURS = 24;

    protected $signature = 'moashir:prune-otp-codes';

    protected $description = 'Delete one-time codes that expired more than 24 hours ago.';

    public function handle(): int
    {
        $deleted = ScalarValue::int(OtpCode::query()->where('expires_at', '<', Date::now()->subHours(self::RETENTION_HOURS))->delete());
        $this->components->info(sprintf('%d code(s) deleted.', $deleted));

        return self::SUCCESS;
    }
}
