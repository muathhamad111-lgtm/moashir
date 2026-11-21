<?php

declare(strict_types=1);

use App\Services\Identity\PhoneIndexRebuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string VERIFIED_UNIQUE = 'users_verified_phone_index_unique';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('phone_index', 64)->nullable();
        });

        app(PhoneIndexRebuilder::class)->rebuild();

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON users (phone_index) WHERE phone_index IS NOT NULL AND phone_verified_at IS NOT NULL AND deleted_at IS NULL',
            self::VERIFIED_UNIQUE,
        ));
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::VERIFIED_UNIQUE));

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone_index');
        });
    }
};
