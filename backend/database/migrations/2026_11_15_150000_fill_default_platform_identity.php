<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array DEFAULT_NAME = ['ar' => 'مؤشر', 'en' => 'Moashir'];

    private const array DEFAULT_DESCRIPTION = [
        'ar' => 'منصة رقمية لقياس النضج المؤسسي للجمعيات والمؤسسات الأهلية والأوقاف وفق نموذج التميّز في العمل الخيري.',
        'en' => 'A digital platform that measures the institutional maturity of civil associations, civil foundations and endowments against the Charitable Work Excellence Model.',
    ];

    public function up(): void
    {
        DB::table('site_settings')->whereNull('platform_name')->update(['platform_name' => $this->json(self::DEFAULT_NAME)]);
        DB::table('site_settings')->whereNull('platform_description')->update(['platform_description' => $this->json(self::DEFAULT_DESCRIPTION)]);
    }

    public function down(): void
    {
        DB::table('site_settings')->whereRaw('platform_name = ?::jsonb', [$this->json(self::DEFAULT_NAME)])->update(['platform_name' => null]);
        DB::table('site_settings')->whereRaw('platform_description = ?::jsonb', [$this->json(self::DEFAULT_DESCRIPTION)])->update(['platform_description' => null]);
    }

    /**
     * @param  array<string, string>  $value
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
};
