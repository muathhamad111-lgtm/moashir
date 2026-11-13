<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array PAGE_KEYS = ['home', 'accredited_intro', 'advanced_intro', 'bag_details', 'lenses', 'kpis'];

    private const array SECTION_TYPES = [
        'hero', 'paths', 'excellence_model', 'how_it_works', 'bag_promo', 'leadership_promo', 'lenses_brief', 'kpi_promo',
        'partners', 'contact', 'footer', 'assistant', 'rich_text', 'faq', 'pricing_box', 'cycle_box', 'winners', 'logos',
    ];

    private const array LOCALIZED_COLUMNS = ['title', 'subtitle', 'description', 'cta_label'];

    public function up(): void
    {
        $this->createPages();
        $this->createSections();
        $this->createItems();
        $this->createSiteSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('cms_section_items');
        Schema::dropIfExists('cms_sections');
        Schema::dropIfExists('cms_pages');
    }

    private function createPages(): void
    {
        Schema::create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 30)->unique();
            $table->jsonb('title')->nullable();
            $table->jsonb('seo')->nullable();
            $table->boolean('is_published')->default(false);
            $table->jsonb('published_content')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('content_updated_at')->nullable();
            $table->boolean('has_draft_changes')->default(false);
            $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement(sprintf('ALTER TABLE cms_pages ADD CONSTRAINT cms_pages_key_check CHECK (key IN (%s))', $this->quoted(self::PAGE_KEYS)));
        DB::statement("ALTER TABLE cms_pages ADD CONSTRAINT cms_pages_title_check CHECK (title IS NULL OR jsonb_typeof(title) = 'object')");
        DB::statement('ALTER TABLE cms_pages ADD CONSTRAINT cms_pages_published_check CHECK (NOT is_published OR (published_content IS NOT NULL AND published_at IS NOT NULL))');

        $now = now();
        DB::table('cms_pages')->insert(array_map(static fn (string $key): array => [
            'key' => $key,
            'is_published' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::PAGE_KEYS));
    }

    private function createSections(): void
    {
        Schema::create('cms_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->restrictOnDelete();
            $table->string('key', 40);
            $table->string('type', 25);
            $table->boolean('is_enabled')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $this->contentColumns($table);
            $table->string('background', 30)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['cms_page_id', 'sort_order']);
        });

        DB::statement(sprintf('ALTER TABLE cms_sections ADD CONSTRAINT cms_sections_type_check CHECK (type IN (%s))', $this->quoted(self::SECTION_TYPES)));
        DB::statement("ALTER TABLE cms_sections ADD CONSTRAINT cms_sections_key_check CHECK (key ~ '^[a-z][a-z0-9_-]*$')");
        DB::statement('CREATE UNIQUE INDEX cms_sections_page_key_unique ON cms_sections (cms_page_id, key) WHERE deleted_at IS NULL');
        $this->addObjectChecks('cms_sections');
    }

    private function createItems(): void
    {
        Schema::create('cms_section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_section_id')->constrained('cms_sections')->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $this->contentColumns($table);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['cms_section_id', 'sort_order']);
        });

        $this->addObjectChecks('cms_section_items');
    }

    private function createSiteSettings(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->smallInteger('id')->primary();
            $table->jsonb('social')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('contact')->nullable();
            $table->jsonb('footer_note')->nullable();
            $table->jsonb('quick_links')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('seo')->nullable();
            $table->smallInteger('featured_kpi_limit')->default(6);
            $table->boolean('show_featured_kpis')->default(true);
            $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE site_settings ADD CONSTRAINT site_settings_singleton_check CHECK (id = 1)');
        DB::statement('ALTER TABLE site_settings ADD CONSTRAINT site_settings_featured_limit_check CHECK (featured_kpi_limit BETWEEN 3 AND 6)');
        DB::statement("ALTER TABLE site_settings ADD CONSTRAINT site_settings_lists_check CHECK (jsonb_typeof(social) = 'array' AND jsonb_typeof(quick_links) = 'array')");

        DB::table('site_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function contentColumns(Blueprint $table): void
    {
        foreach (self::LOCALIZED_COLUMNS as $column) {
            $table->jsonb($column)->nullable();
        }

        $table->string('cta_url', 300)->nullable();
        $table->string('icon', 60)->nullable();
        $table->foreignId('image_file_id')->nullable()->index()->constrained('stored_files')->nullOnDelete();
        $table->jsonb('meta')->nullable();
        $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
    }

    private function addObjectChecks(string $table): void
    {
        foreach ([...self::LOCALIZED_COLUMNS, 'meta'] as $column) {
            DB::statement(sprintf("ALTER TABLE %s ADD CONSTRAINT %s_%s_check CHECK (%s IS NULL OR jsonb_typeof(%s) = 'object')", $table, $table, $column, $column, $column));
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function quoted(array $values): string
    {
        return implode(', ', array_map(static fn (string $value): string => "'".$value."'", $values));
    }
};
