<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class RateLimitServiceProvider extends ServiceProvider
{
    private const int TEN_MINUTES = 10;

    public function boot(): void
    {
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by('api|'.self::subject($request)));

        RateLimiter::for('public', static fn (Request $request): Limit => Limit::perMinute(120)->by('public|'.$request->ip()));

        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by('login|'.self::emailFingerprint($request).'|'.$request->ip()));

        RateLimiter::for('otp-request', static fn (Request $request): array => [
            Limit::perMinutes(self::TEN_MINUTES, 3)->by('otp-email|'.self::emailFingerprint($request)),
            Limit::perMinutes(self::TEN_MINUTES, 10)->by('otp-ip|'.$request->ip()),
        ]);

        RateLimiter::for('otp-resend', static fn (Request $request): Limit => Limit::perMinutes(self::TEN_MINUTES, 5)->by('otp-resend|'.$request->ip()));

        RateLimiter::for('otp-verify', static fn (Request $request): Limit => Limit::perMinute(10)->by('otp-verify|'.$request->ip()));

        RateLimiter::for('mfa', static fn (Request $request): Limit => Limit::perMinute(5)->by('mfa|'.$request->ip()));

        RateLimiter::for('password-reset', static fn (Request $request): array => [
            Limit::perMinutes(self::TEN_MINUTES, 3)->by('password-reset-email|'.self::emailFingerprint($request)),
            Limit::perMinutes(self::TEN_MINUTES, 10)->by('password-reset-ip|'.$request->ip()),
        ]);

        RateLimiter::for('invitation', static fn (Request $request): Limit => Limit::perMinute(5)->by('invitation|'.$request->ip()));

        RateLimiter::for('search', static fn (Request $request): Limit => Limit::perMinute(60)->by('search|'.self::subject($request)));

        RateLimiter::for('criteria-write', static fn (Request $request): Limit => Limit::perMinute(60)->by('criteria-write|'.self::subject($request)));

        RateLimiter::for('assessment-write', static fn (Request $request): Limit => Limit::perMinute(60)->by('assessment-write|'.self::subject($request)));

        RateLimiter::for('sensitive', static fn (Request $request): Limit => Limit::perMinute(20)->by('sensitive|'.self::subject($request)));

        RateLimiter::for('register', static fn (Request $request): Limit => Limit::perHour(5)->by('register|'.$request->ip()));

        RateLimiter::for('email-change', static fn (Request $request): Limit => Limit::perMinutes(self::TEN_MINUTES, 3)->by('email-change|'.self::subject($request)));

        RateLimiter::for('integration-probe', static fn (Request $request): Limit => Limit::perMinute(max(1, ScalarValue::int(config('moashir.integrations.probe_per_minute'), 3)))
            ->by('integration-probe|'.$request->route()?->getName().'|'.self::subject($request)));

        RateLimiter::for('webhooks', static fn (Request $request): Limit => Limit::perMinute(max(1, ScalarValue::int(config('moashir.finance.webhooks_per_minute'), 120)))->by('webhooks|'.$request->ip()));

        RateLimiter::for('payment-verify', static fn (Request $request): Limit => Limit::perMinute(30)->by('payment-verify|'.self::subject($request)));

        RateLimiter::for('uploads', static fn (Request $request): Limit => Limit::perMinute(30)->by('uploads|'.self::subject($request)));

        RateLimiter::for('downloads', static fn (Request $request): Limit => Limit::perMinute(30)->by('downloads|'.self::subject($request)));

        RateLimiter::for('exports', static fn (Request $request): Limit => Limit::perMinute(5)->by('exports|'.self::subject($request)));
    }

    private static function subject(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User ? 'user:'.$user->id : 'ip:'.$request->ip();
    }

    private static function emailFingerprint(Request $request): string
    {
        return hash('sha256', mb_strtolower(trim($request->string('email')->toString())));
    }
}
