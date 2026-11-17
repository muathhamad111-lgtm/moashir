<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array PREVIOUS_TYPES = [
        'hero', 'paths', 'excellence_model', 'how_it_works', 'bag_promo', 'leadership_promo', 'lenses_brief', 'kpi_promo',
        'partners', 'contact', 'footer', 'assistant', 'rich_text', 'faq', 'pricing_box', 'cycle_box', 'winners', 'logos',
    ];

    private const array ADDED_TYPES = ['about', 'levels'];

    public function up(): void
    {
        $this->replaceCheck([...self::PREVIOUS_TYPES, ...self::ADDED_TYPES]);
    }

    public function down(): void
    {
        if (DB::table('cms_sections')->whereIn('type', self::ADDED_TYPES)->exists()) {
            throw new RuntimeException('Content sections of the about or levels type exist (including removed ones); remove them before rolling this migration back.');
        }

        $this->replaceCheck(self::PREVIOUS_TYPES);
    }

    /**
     * @param  list<string>  $types
     */
    private function replaceCheck(array $types): void
    {
        $quoted = implode(', ', array_map(static fn (string $type): string => "'".$type."'", $types));

        DB::statement('ALTER TABLE cms_sections DROP CONSTRAINT IF EXISTS cms_sections_type_check');
        DB::statement(sprintf('ALTER TABLE cms_sections ADD CONSTRAINT cms_sections_type_check CHECK (type IN (%s))', $quoted));
    }
};
