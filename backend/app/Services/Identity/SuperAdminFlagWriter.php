<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Exceptions\Console\SuperAdminCommandRejected;
use App\Models\Identity\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;

final readonly class SuperAdminFlagWriter
{
    private const array QUEUE_COMMANDS = ['queue:work', 'queue:listen', 'horizon', 'horizon:work', 'schedule:run', 'schedule:work'];

    public function __construct(private Application $app, private UserSessionTerminator $sessions) {}

    public function grant(User $user): void
    {
        $this->write($user, [
            'is_super_admin' => true,
            'mfa_required' => true,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function revoke(User $user): void
    {
        $this->write($user, ['is_super_admin' => false, 'mfa_required' => false]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(User $user, array $attributes): void
    {
        $this->assertConsole();

        DB::transaction(function () use ($user, $attributes): void {
            DB::select("SELECT set_config('moashir.console_context', 'on', true)");
            $user->forceFill($attributes)->save();
            $this->sessions->terminateAll($user);
        });
    }

    private function assertConsole(): void
    {
        if (! $this->app->runningInConsole() || $this->app->runningConsoleCommand(...self::QUEUE_COMMANDS)) {
            throw SuperAdminCommandRejected::outsideConsole();
        }
    }
}
