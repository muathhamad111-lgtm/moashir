<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Criteria\BoundAssessmentCounter;
use App\Contracts\Criteria\OpenAssessmentCounter;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Services\Assessments\AssessmentDeadlines;
use App\Services\Assessments\BoundAssessmentCounts;
use App\Services\Assessments\CompletionDeadlines;
use App\Services\Assessments\CriteriaUpdateHold;
use App\Services\Assessments\OpenAssessmentRequests;
use App\Services\Assessments\ReviewedRequestEraser;
use App\Services\Assessments\SelfAssessmentEraser;
use App\Services\Entities\EntityDeadlineFreezer;
use App\Services\Entities\EntityEraser;
use App\Services\Entities\EntityRestoreSteps;
use App\Services\Entities\OpenRequestDirectory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AssessmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OpenAssessmentCounter::class, BoundAssessmentCounts::class);
        $this->app->bind(BoundAssessmentCounter::class, BoundAssessmentCounts::class);
        $this->app->tag([OpenAssessmentRequests::class], OpenRequestDirectory::TAG);
        $this->app->tag([SelfAssessmentEraser::class, ReviewedRequestEraser::class], EntityEraser::TAG);
        $this->app->tag([AssessmentDeadlines::class, CompletionDeadlines::class], EntityDeadlineFreezer::TAG);
        $this->app->tag([CriteriaUpdateHold::class], EntityRestoreSteps::TAG);
    }

    public function boot(): void
    {
        Route::bind('assessment', static fn (string $value): Assessment => Assessment::query()
            ->with(['entity', 'criteriaVersion', 'currentResult'])
            ->whereKey((int) $value)
            ->firstOrFail());

        Route::bind('evidence', static fn (string $value): EvidenceFile => EvidenceFile::query()
            ->with(['storedFile', 'assessment'])
            ->where('public_id', $value)
            ->firstOrFail());

        Route::bind('bundle', static fn (string $value): EvidenceBundle => EvidenceBundle::query()
            ->with('storedFile')
            ->where('public_id', $value)
            ->firstOrFail());
    }
}
