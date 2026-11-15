<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array DEFAULT_HEADER_LINKS = [
        ['label' => ['ar' => 'الرئيسية', 'en' => 'Home'], 'url' => '/'],
        ['label' => ['ar' => 'جولة في المنصة', 'en' => 'Platform tour'], 'url' => '/#tour'],
        ['label' => ['ar' => 'مسارات التقييم', 'en' => 'Assessment paths'], 'url' => '/#paths'],
        ['label' => ['ar' => 'نموذج التميّز', 'en' => 'Excellence model'], 'url' => '/#model'],
        ['label' => ['ar' => 'مؤشرات الأداء', 'en' => 'Performance Indicators'], 'url' => '/kpis'],
        ['label' => ['ar' => 'تواصل معنا', 'en' => 'Contact us'], 'url' => '/#contact'],
    ];

    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->jsonb('platform_name')->nullable();
            $table->jsonb('platform_description')->nullable();
            $table->jsonb('header_links')->default(DB::raw("'[]'::jsonb"));
        });

        DB::statement("ALTER TABLE site_settings ADD CONSTRAINT site_settings_header_links_check CHECK (jsonb_typeof(header_links) = 'array')");

        DB::table('site_settings')->update(['header_links' => json_encode(self::DEFAULT_HEADER_LINKS, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE site_settings DROP CONSTRAINT IF EXISTS site_settings_header_links_check');

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['platform_name', 'platform_description', 'header_links']);
        });
    }
};
