<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('rolls the Task-51 migrations back and forward again', function (string $file, string $table, array $columns): void {
    $migration = require database_path('migrations/'.$file.'.php');

    $migration->down();
    expect(Schema::hasColumns($table, $columns))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumns($table, $columns))->toBeTrue();
})->with([
    'identifier hash' => ['2026_11_04_160100_add_identifier_hash_to_authentication_events_table', 'authentication_events', ['identifier_type', 'identifier_hash']],
    'phone index' => ['2026_11_04_160200_add_phone_index_to_users_table', 'users', ['phone_index']],
    'unified number' => ['2026_11_04_160300_add_unified_number_to_entities_table', 'entities', ['unified_number']],
]);

it('rolls the second-factor purpose back and forward again', function (): void {
    $migration = require database_path('migrations/2026_11_04_160000_add_login_second_factor_to_otp_codes.php');

    $migration->down();
    $migration->up();

    signInWith(ownerOf(entityWithOwner())->email, defaultPassword())->assertOk()->assertJsonPath('data.step', 'otp_required');
});
