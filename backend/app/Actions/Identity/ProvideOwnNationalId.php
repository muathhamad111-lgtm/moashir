<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Models\Identity\User;
use App\Services\Identity\NationalIdAssigner;
use App\Support\ValueObjects\NationalId;
use Illuminate\Support\Facades\DB;

final readonly class ProvideOwnNationalId
{
    public function __construct(private NationalIdAssigner $assigner) {}

    public function handle(User $user, NationalId $nationalId): NationalId
    {
        DB::transaction(function () use ($user, $nationalId): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assigner->assignFirst($locked, $nationalId);
        });
        $user->refresh();

        return $nationalId;
    }
}
