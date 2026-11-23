<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\CycleOpenBlock;
use App\Enums\Audit\AuditEvent;
use App\Events\Award\CycleOpenDeferred;
use App\Models\Award\AwardCycle;
use App\Services\Audit\AuditRecorder;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Date;

final readonly class DeferScheduledCycleOpen
{
    private const string KEY = 'award:cycle-open-deferred:%d:%s';

    private const int DEDUPE_SECONDS = 86_400;

    public function __construct(private AuditRecorder $audit, private Config $config, private Cache $cache) {}

    public function handle(AwardCycle $cycle, CycleOpenBlock $block): bool
    {
        $day = Date::now()->setTimezone($this->config->string('app.display_timezone'))->toDateString();

        if (! $this->cache->add(sprintf(self::KEY, $cycle->id, $day), true, self::DEDUPE_SECONDS)) {
            return false;
        }

        $this->audit->record(new AuditEntry(AuditEvent::CycleOpenDeferred, $cycle, [], [
            'code' => $block->code(),
            'concurrency' => $block->concurrency->value,
            'blocking_cycle_id' => $block->blockingCycle->id,
        ]));
        CycleOpenDeferred::dispatch($cycle, $block->blockingCycle);

        return true;
    }
}
