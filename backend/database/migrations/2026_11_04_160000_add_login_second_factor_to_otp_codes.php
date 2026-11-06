<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string BEFORE = "'login', 'verify_email', 'verify_phone', 'password_reset', 'email_change'";

    private const string AFTER = "'login', 'login_second_factor', 'verify_email', 'verify_phone', 'password_reset', 'email_change'";

    public function up(): void
    {
        $this->replaceCheck(self::AFTER);
    }

    public function down(): void
    {
        DB::table('otp_codes')->where('purpose', 'login_second_factor')->delete();

        $this->replaceCheck(self::BEFORE);
    }

    private function replaceCheck(string $purposes): void
    {
        DB::statement('ALTER TABLE otp_codes DROP CONSTRAINT IF EXISTS otp_codes_purpose_check');
        DB::statement(sprintf('ALTER TABLE otp_codes ADD CONSTRAINT otp_codes_purpose_check CHECK (purpose IN (%s))', $purposes));
    }
};
