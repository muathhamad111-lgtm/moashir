<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accredited certificate issuance moves from an on/off switch to a mode (operator 2026-11-19):
 * on becomes «automatic» (issued when the assessment is completed), off becomes «manual» (the
 * assigned assessor issues it). The settings row keeps its id and audit trail.
 */
return new class extends Migration
{
    private const string LEGACY_KEY = 'certificates.accredited_issuance_enabled';

    private const string MODE_KEY = 'certificates.accredited_issuance_mode';

    public function up(): void
    {
        $setting = DB::table('settings')->where('key', self::LEGACY_KEY)->first(['id', 'value']);

        if ($setting === null) {
            return;
        }

        $mode = $this->storedValue($setting->value) === false ? 'manual' : 'automatic';

        DB::table('settings')->where('id', $setting->id)->update([
            'key' => self::MODE_KEY,
            'type' => 'string',
            'value' => json_encode($mode),
        ]);
    }

    public function down(): void
    {
        $setting = DB::table('settings')->where('key', self::MODE_KEY)->first(['id', 'value']);

        if ($setting === null) {
            return;
        }

        DB::table('settings')->where('id', $setting->id)->update([
            'key' => self::LEGACY_KEY,
            'type' => 'boolean',
            'value' => json_encode($this->storedValue($setting->value) !== 'manual'),
        ]);
    }

    private function storedValue(mixed $raw): mixed
    {
        return is_string($raw) ? json_decode($raw) : null;
    }
};
