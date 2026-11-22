<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A partner keeps only its name, logo (image_file_id), link, visibility and order. The other content fields are no
 * longer edited or shown for partners, so they are cleared. The cleared values were never visible on the site, so
 * down() does not restore them.
 */
return new class extends Migration
{
    private const array UNUSED_FIELDS = ['subtitle', 'description', 'cta_label', 'icon', 'meta'];

    public function up(): void
    {
        DB::table('cms_section_items')
            ->whereIn('cms_section_id', DB::table('cms_sections')->where('type', 'partners')->select('id'))
            ->update(array_fill_keys(self::UNUSED_FIELDS, null));
    }

    public function down(): void {}
};
