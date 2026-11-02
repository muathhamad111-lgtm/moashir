<?php

declare(strict_types=1);

namespace App\Jobs\Assessments;

use App\Actions\Assessments\BuildEvidenceBundle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class BuildEvidenceBundleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $bundleId) {}

    public function uniqueId(): string
    {
        return 'evidence-bundle:'.$this->bundleId;
    }

    public function handle(BuildEvidenceBundle $build): void
    {
        $build->handle($this->bundleId);
    }

    public function failed(?Throwable $exception): void
    {
        app(BuildEvidenceBundle::class)->markFailed($this->bundleId);
    }
}
