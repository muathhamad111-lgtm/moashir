<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Identity\OtpCode;
use App\Models\Identity\RegistrationEmailVerification;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:prune-otp-codes')]
final class PruneExpiredOtpCodes extends Command
{
    private const int RETENTION_HOURS = 24;

    protected $signature = 'moashir:prune-otp-codes';

    protected $description = 'Delete one-time codes and registration e-mail checks that expired more than 24 hours ago.';

    public function handle(): int
    {
        $cutoff = Date::now()->subHours(self::RETENTION_HOURS);
        $deleted = ScalarValue::int(OtpCode::query()->where('expires_at', '<', $cutoff)->delete());
        $checks = ScalarValue::int(RegistrationEmailVerification::query()
            ->where('expires_at', '<', $cutoff)
            ->where(static fn ($query) => $query->whereNull('token_expires_at')->orWhere('token_expires_at', '<', $cutoff))
            ->delete());
        $this->components->info(sprintf('%d code(s) and %d registration e-mail check(s) deleted.', $deleted, $checks));

        return self::SUCCESS;
    }
}
