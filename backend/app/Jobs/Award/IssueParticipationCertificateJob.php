<?php

declare(strict_types=1);

namespace App\Jobs\Award;

use App\Actions\Award\IssueParticipationCertificate;
use App\Models\Assessments\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * The participation certificate of one request, issued after its initial assessment completes.
 * Idempotent: a retry or a duplicate run issues nothing new.
 */
final class IssueParticipationCertificateJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $assessmentId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'issue-participation-certificate:'.$this->assessmentId;
    }

    public function handle(IssueParticipationCertificate $action): void
    {
        $model = Assessment::query()->find($this->assessmentId);

        if ($model instanceof Assessment) {
            $action->handle($model);
        }
    }
}
