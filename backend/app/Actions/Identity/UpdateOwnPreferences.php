<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\PreferenceChanges;
use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOwnPreferences
{
    public function __construct(private SaveTableColumns $saveTableColumns) {}

    public function handle(User $user, PreferenceChanges $changes): User
    {
        DB::transaction(function () use ($user, $changes): void {
            $user->fill(array_filter([
                'locale' => $changes->locale,
                'theme' => $changes->theme,
            ], static fn (mixed $value): bool => $value !== null))->save();

            $this->saveTableColumns->handle($user, $changes->tableColumns);
        });

        return $user->load('tableColumns');
    }
}
