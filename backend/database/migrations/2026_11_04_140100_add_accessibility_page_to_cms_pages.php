<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array PREVIOUS_KEYS = ['home', 'accredited_intro', 'advanced_intro', 'bag_details', 'lenses', 'kpis'];

    private const string NEW_KEY = 'accessibility';

    public function up(): void
    {
        $this->replaceKeyCheck([...self::PREVIOUS_KEYS, self::NEW_KEY]);

        DB::table('cms_pages')->insertOrIgnore([
            'key' => self::NEW_KEY,
            'is_published' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $page = DB::table('cms_pages')->where('key', self::NEW_KEY)->value('id');

        if ($page !== null) {
            $sections = DB::table('cms_sections')->where('cms_page_id', $page)->pluck('id');
            DB::table('cms_section_items')->whereIn('cms_section_id', $sections)->delete();
            DB::table('cms_sections')->where('cms_page_id', $page)->delete();
            DB::table('cms_pages')->where('id', $page)->delete();
        }

        $this->replaceKeyCheck(self::PREVIOUS_KEYS);
    }

    /**
     * @param  list<string>  $keys
     */
    private function replaceKeyCheck(array $keys): void
    {
        $quoted = implode(', ', array_map(static fn (string $key): string => "'".$key."'", $keys));

        DB::statement('ALTER TABLE cms_pages DROP CONSTRAINT IF EXISTS cms_pages_key_check');
        DB::statement(sprintf('ALTER TABLE cms_pages ADD CONSTRAINT cms_pages_key_check CHECK (key IN (%s))', $quoted));
    }
};
