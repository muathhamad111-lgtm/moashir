<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Support\ScalarValue;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;

final readonly class DraftNoticeThrottle
{
    private const string KEY_PREFIX = 'assessments:draft-notice:';

    public function __construct(private Cache $cache, private Config $config) {}

    public function allows(Assessment $assessment): bool
    {
        $minutes = max(1, ScalarValue::int($this->config->get('moashir.assessment.draft_notice_cooldown_minutes'), 60));

        return $this->cache->add(self::KEY_PREFIX.$assessment->id, true, Date::now()->addMinutes($minutes));
    }
}
