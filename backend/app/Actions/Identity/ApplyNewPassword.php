<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;
use App\Services\Identity\AuthSettings;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ApplyNewPassword
{
    public function __construct(private AuthSettings $settings) {}

    public function handle(User $user, string $plainPassword): void
    {
        DB::transaction(function () use ($user, $plainPassword): void {
            $user->forceFill([
                'password' => $plainPassword,
                'password_changed_at' => Date::now(),
                'password_change_required' => false,
            ])->save();

            $user->passwordHistories()->create(['password_hash' => $user->getAuthPassword()]);
            $this->pruneHistory($user);
        });
    }

    private function pruneHistory(User $user): void
    {
        $keptIds = $user->passwordHistories()
            ->latest('created_at')
            ->latest('id')
            ->limit($this->settings->passwordHistoryDepth())
            ->pluck('id');

        $user->passwordHistories()->whereNotIn('id', $keptIds)->delete();
    }
}
