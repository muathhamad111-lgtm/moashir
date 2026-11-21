<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Identity\PasswordResetLink;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:prune-password-reset-links')]
final class PruneExpiredPasswordResetLinks extends Command
{
    protected $signature = 'moashir:prune-password-reset-links';

    protected $description = 'Delete password reset links that have expired.';

    public function handle(): int
    {
        $deleted = ScalarValue::int(PasswordResetLink::query()->where('expires_at', '<=', Date::now())->delete());
        $this->components->info(sprintf('%d expired password reset link(s) deleted.', $deleted));

        return self::SUCCESS;
    }
}
