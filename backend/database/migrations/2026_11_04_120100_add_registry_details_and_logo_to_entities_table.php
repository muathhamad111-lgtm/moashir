<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CHECKS = [
        'license_number' => "license_number IS NULL OR license_number ~ '^[0-9]{3,20}$'",
        'founded_on' => "founded_on IS NULL OR (founded_on >= DATE '1900-01-01' AND EXTRACT(YEAR FROM founded_on) = founding_year)",
        'website_url' => "website_url IS NULL OR website_url ~ '^https://[^[:space:]]+$'",
    ];

    private const string LICENSE_INDEX = 'entities_license_number_unique';

    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->string('license_number', 20)->nullable();
            $table->date('founded_on')->nullable();
            $table->string('website_url', 255)->nullable();
            $table->foreignId('logo_file_id')->nullable()->index()->constrained('stored_files')->restrictOnDelete();
        });

        foreach (self::CHECKS as $name => $expression) {
            DB::statement(sprintf('ALTER TABLE entities ADD CONSTRAINT entities_%s_check CHECK (%s)', $name, $expression));
        }

        DB::statement(sprintf('CREATE UNIQUE INDEX %s ON entities (license_number) WHERE license_number IS NOT NULL', self::LICENSE_INDEX));
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX IF EXISTS %s', self::LICENSE_INDEX));

        foreach (array_keys(self::CHECKS) as $name) {
            DB::statement(sprintf('ALTER TABLE entities DROP CONSTRAINT IF EXISTS entities_%s_check', $name));
        }

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('logo_file_id');
            $table->dropColumn(['license_number', 'founded_on', 'website_url']);
        });
    }
};
