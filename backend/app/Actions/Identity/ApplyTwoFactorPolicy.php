<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;
use App\Services\Identity\MfaRequirement;
use Illuminate\Database\Eloquent\Collection;

final readonly class ApplyTwoFactorPolicy
{
    private const int CHUNK = 200;

    public function __construct(private MfaRequirement $requirement) {}

    public function handle(): void
    {
        User::query()->with('roles')->chunkById(self::CHUNK, function (Collection $users): void {
            foreach ($users as $user) {
                $this->apply($user);
            }
        });
    }

    private function apply(User $user): void
    {
        $required = $this->requirement->isRequiredFor($user->roleKeys());

        if ($user->mfa_required !== $required) {
            User::withoutAuditing(static fn (): bool => $user->forceFill(['mfa_required' => $required])->save());
        }
    }
}
