<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Certificates\Certificate;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Collection;

/**
 * When a System Administrator turns participation certificates on for a running cycle, the requests whose initial
 * assessment is already complete (withdrawn ones included) get theirs at once, through the same idempotent issue.
 */
final readonly class BackfillParticipationCertificates
{
    private const int CHUNK = 200;

    public function __construct(private IssueParticipationCertificate $issue, private EntityFreezeGuard $freezeGuard) {}

    public function handle(AwardCycle $cycle): int
    {
        $issued = 0;

        $this->freezeGuard->excludeFrozen(Assessment::query()->inCycle($cycle->id)->whereNotNull('current_result_snapshot_id'))
            ->chunkById(self::CHUNK, function (Collection $requests) use (&$issued): void {
                foreach ($requests as $request) {
                    $issued += $this->issue->handle($request) instanceof Certificate ? 1 : 0;
                }
            });

        return $issued;
    }
}
