<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Assessments\AssessmentReopenStep;
use App\Contracts\Entities\EntityLogoRetention;
use App\Models\Certificates\Certificate;
use App\Services\Certificates\CertificateLogoRetention;
use App\Services\Certificates\CertificateSuspender;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CertificatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssessmentReopenStep::class, CertificateSuspender::class);
        $this->app->bind(EntityLogoRetention::class, CertificateLogoRetention::class);
    }

    public function boot(): void
    {
        Route::bind('certificate', static fn (string $value): Certificate => Certificate::query()
            ->with('assessment.entity')
            ->where('public_id', $value)
            ->firstOrFail());
    }
}
