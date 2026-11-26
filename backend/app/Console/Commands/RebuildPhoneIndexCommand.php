<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Identity\PhoneIndexRebuilder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:identity:rebuild-phone-index')]
final class RebuildPhoneIndexCommand extends Command
{
    protected $signature = 'moashir:identity:rebuild-phone-index';

    protected $description = 'Recompute the keyed mobile-number index of every account (run after rotating APP_KEY).';

    public function handle(PhoneIndexRebuilder $rebuilder): int
    {
        $result = $rebuilder->rebuild();

        $this->components->info(sprintf('%d mobile number(s) indexed, %d unreadable.', $result->indexed, $result->unreadable));

        return $result->unreadable === 0 ? self::SUCCESS : self::FAILURE;
    }
}
