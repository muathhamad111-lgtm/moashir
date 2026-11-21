<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Platform\DemoPurgeCount;
use App\Models\Identity\User;
use App\Services\Entities\ErasedOwnerAnonymizer;
use App\Support\Demo\DemoPurgeTally;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class DemoUserPurger
{
    private const string FOREIGN_KEY_VIOLATION = '23503';

    private const string PSEUDONYM_PREFIX = 'PURGED-';

    public function __construct(private DemoAwardCyclePurger $cycles, private ErasedOwnerAnonymizer $anonymizer) {}

    public function purge(User $user, DemoPurgeTally $tally): void
    {
        $tally->add(DemoPurgeCount::AwardCycles, $this->cycles->purgeUnusedDraftsCreatedBy($user->id));

        try {
            DB::transaction(fn () => $this->delete($user));
            $tally->add(DemoPurgeCount::UsersDeleted, 1);
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::FOREIGN_KEY_VIOLATION) {
                throw $exception;
            }

            $this->anonymizer->anonymize($user, self::PSEUDONYM_PREFIX.Str::upper(Str::random(12)));
            $tally->add(DemoPurgeCount::UsersRetired, 1);
        }
    }

    private function delete(User $user): void
    {
        $user->roles()->detach();
        $user->permissions()->detach();
        DB::table('password_reset_tokens')->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->delete();
        User::withTrashed()->whereKey($user->id)->forceDelete();
    }
}
