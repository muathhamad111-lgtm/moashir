<?php

declare(strict_types=1);

namespace App\Enums\Platform;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Console\WipeCommand;

enum DestructiveDatabaseCommand: string
{
    public const string CONFIRMATION_OPTION = 'confirm-database';

    case Fresh = 'migrate:fresh';
    case Refresh = 'migrate:refresh';
    case Reset = 'migrate:reset';
    case Rollback = 'migrate:rollback';
    case Wipe = 'db:wipe';
    case Seed = 'db:seed';

    /**
     * @return class-string<Command>
     */
    public function commandClass(): string
    {
        return match ($this) {
            self::Fresh => FreshCommand::class,
            self::Refresh => RefreshCommand::class,
            self::Reset => ResetCommand::class,
            self::Rollback => RollbackCommand::class,
            self::Wipe => WipeCommand::class,
            self::Seed => SeedCommand::class,
        };
    }

    public function isSeeding(): bool
    {
        return $this === self::Seed;
    }
}
