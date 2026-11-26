<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use Carbon\CarbonInterface;

final readonly class PaidDraftReminder
{
    public function __construct(public int $assessmentId, public CarbonInterface $remindedAt, public CarbonInterface $nextRemindAt) {}
}
