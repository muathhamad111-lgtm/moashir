<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array COPIES = [
        'files.max_size_mb' => 'upload_max_file_mb',
        'files.evidence_allowed_types' => 'evidence_allowed_types',
        'files.evidence_max_files_per_sub' => 'evidence_max_files_per_sub',
    ];

    private const array PATHS = ['accredited', 'advanced'];

    public function up(): void
    {
        foreach (self::COPIES as $sharedKey => $field) {
            foreach (self::PATHS as $path) {
                DB::statement(<<<'SQL'
                    INSERT INTO settings ("group", key, type, value, is_public, updated_by_user_id, created_at, updated_at)
                    SELECT 'assessment', ?, type, value, false, updated_by_user_id, now(), now()
                    FROM settings
                    WHERE key = ?
                    ON CONFLICT (key) DO NOTHING
                    SQL, ['assessment.'.$path.'_'.$field, $sharedKey]);
            }
        }
    }

    public function down(): void
    {
        $keys = [];

        foreach (self::COPIES as $field) {
            foreach (self::PATHS as $path) {
                $keys[] = 'assessment.'.$path.'_'.$field;
            }
        }

        DB::table('settings')->whereIn('key', $keys)->delete();
    }
};
