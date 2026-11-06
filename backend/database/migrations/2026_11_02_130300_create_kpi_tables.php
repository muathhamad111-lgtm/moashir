<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array STATUSES = ['excellent', 'good', 'needs_improvement', 'behind_target'];

    private const array COLOR_TOKENS = ['primary', 'success', 'info', 'warning', 'danger'];

    private const array LOCALIZED_COLUMNS = ['short_description', 'full_description', 'unit', 'owner', 'data_source', 'calc_method', 'notes'];

    public function up(): void
    {
        $this->createCategories();
        $this->createTypes();
        $this->createKpis();
        $this->createValues();
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_values');
        Schema::dropIfExists('kpis');
        Schema::dropIfExists('kpi_types');
        Schema::dropIfExists('kpi_categories');
    }

    private function createCategories(): void
    {
        Schema::create('kpi_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60);
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('sort_order');
        });

        DB::statement('CREATE UNIQUE INDEX kpi_categories_key_unique ON kpi_categories (key) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE kpi_categories ADD CONSTRAINT kpi_categories_key_check CHECK (key ~ '^[a-z0-9]+([_-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE kpi_categories ADD CONSTRAINT kpi_categories_name_check CHECK (jsonb_typeof(name) = 'object')");
    }

    private function createTypes(): void
    {
        Schema::create('kpi_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->jsonb('name');
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('sort_order');
        });

        DB::statement("ALTER TABLE kpi_types ADD CONSTRAINT kpi_types_key_check CHECK (key ~ '^[a-z0-9]+([_-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE kpi_types ADD CONSTRAINT kpi_types_name_check CHECK (jsonb_typeof(name) = 'object')");
    }

    private function createKpis(): void
    {
        Schema::create('kpis', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80);
            $table->jsonb('name');
            $table->foreignId('kpi_category_id')->nullable()->index()->constrained('kpi_categories')->restrictOnDelete();
            $table->foreignId('kpi_type_id')->nullable()->index()->constrained('kpi_types')->restrictOnDelete();
            $table->decimal('value', 18, 4)->nullable();
            $table->decimal('target', 18, 4)->nullable();
            $table->decimal('baseline', 18, 4)->nullable();

            foreach (self::LOCALIZED_COLUMNS as $column) {
                $table->jsonb($column)->nullable();
            }

            $table->smallInteger('measurement_year')->nullable()->index();
            $table->string('status', 20)->nullable()->index();
            $table->string('external_link', 300)->nullable();
            $table->string('icon', 60)->nullable();
            $table->foreignId('image_file_id')->nullable()->index()->constrained('stored_files')->nullOnDelete();
            $table->string('card_color_token', 30)->nullable();
            $table->string('badge_color_token', 30)->nullable();
            $table->boolean('show_on_homepage')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->smallInteger('sort_order')->default(0)->index();
            $table->timestampTz('value_updated_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['is_published', 'show_on_homepage']);
        });

        DB::statement('CREATE UNIQUE INDEX kpis_slug_unique ON kpis (slug) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE kpis ADD CONSTRAINT kpis_slug_check CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE kpis ADD CONSTRAINT kpis_name_check CHECK (jsonb_typeof(name) = 'object')");
        DB::statement(sprintf('ALTER TABLE kpis ADD CONSTRAINT kpis_status_check CHECK (status IS NULL OR status IN (%s))', $this->quoted(self::STATUSES)));
        DB::statement(sprintf('ALTER TABLE kpis ADD CONSTRAINT kpis_card_color_check CHECK (card_color_token IS NULL OR card_color_token IN (%s))', $this->quoted(self::COLOR_TOKENS)));
        DB::statement(sprintf('ALTER TABLE kpis ADD CONSTRAINT kpis_badge_color_check CHECK (badge_color_token IS NULL OR badge_color_token IN (%s))', $this->quoted(self::COLOR_TOKENS)));
        DB::statement('ALTER TABLE kpis ADD CONSTRAINT kpis_measurement_year_check CHECK (measurement_year IS NULL OR measurement_year BETWEEN 2000 AND 2100)');
        DB::statement("ALTER TABLE kpis ADD CONSTRAINT kpis_external_link_check CHECK (external_link IS NULL OR external_link LIKE 'https://%')");
        DB::statement('ALTER TABLE kpis ADD CONSTRAINT kpis_published_check CHECK (NOT is_published OR published_at IS NOT NULL)');
    }

    private function createValues(): void
    {
        Schema::create('kpi_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpi_id')->constrained('kpis')->restrictOnDelete();
            $table->decimal('value', 18, 4);
            $table->date('measured_on');
            $table->foreignId('recorded_by_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['kpi_id', 'measured_on']);
        });
    }

    /**
     * @param  list<string>  $values
     */
    private function quoted(array $values): string
    {
        return implode(', ', array_map(static fn (string $value): string => "'".$value."'", $values));
    }
};
