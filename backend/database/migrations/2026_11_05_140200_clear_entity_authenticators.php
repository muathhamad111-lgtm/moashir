<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const int CHUNK = 500;

    private const string REASON = 'entity_second_factor_cancelled';

    public function up(): void
    {
        $cleared = 0;

        $this->entityUsersWithAuthenticator()->chunkById(self::CHUNK, function ($users) use (&$cleared): void {
            $ids = $users->pluck('id')->all();

            DB::table('users')->whereIn('id', $ids)->update([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);

            DB::table('authentication_events')->insert(array_map(static fn (mixed $id): array => [
                'user_id' => $id,
                'type' => 'mfa_disabled',
                'meta' => json_encode(['reason' => self::REASON], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ], $ids));

            $cleared += count($ids);
        });

        Log::info('identity.entity_authenticators_cleared', ['count' => $cleared]);
    }

    public function down(): void {}

    private function entityUsersWithAuthenticator(): Builder
    {
        return DB::table('users')
            ->select('id')
            ->whereNotNull('entity_id')
            ->where(static fn (Builder $query): Builder => $query
                ->whereNotNull('two_factor_secret')
                ->orWhereNotNull('two_factor_recovery_codes')
                ->orWhereNotNull('two_factor_confirmed_at'));
    }
};
