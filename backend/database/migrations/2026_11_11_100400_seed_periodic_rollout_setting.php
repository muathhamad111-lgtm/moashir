<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string KEY = 'periodic.rollout_from';

    private const int QUARTER_MONTHS = 3;

    public function up(): void
    {
        $now = CarbonImmutable::now();

        DB::table('settings')->insertOrIgnore([
            'group' => 'periodic',
            'key' => self::KEY,
            'type' => 'string',
            'value' => json_encode($this->firstFullQuarterAfter($now)),
            'is_public' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::KEY)->delete();
    }

    private function firstFullQuarterAfter(CarbonImmutable $now): string
    {
        $today = $now->setTimezone(config()->string('app.display_timezone'))->startOfDay();
        $quarterStart = $today->firstOfQuarter();

        return $quarterStart->addMonths(self::QUARTER_MONTHS)->toDateString();
    }
};
