<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class NdaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::bind('ndaVersion', static fn (string $value): TermsVersion => TermsVersion::query()
            ->ofKind(TermsKind::Nda)
            ->whereKey(ctype_digit($value) ? (int) $value : 0)
            ->firstOrFail());
    }
}
