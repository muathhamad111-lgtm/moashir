<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Data\Mail\MailFrame;
use App\Enums\Identity\Locale;
use App\Support\FrontendUrl;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;

final readonly class MailFrameComposer
{
    private const string LOGO = 'mail/moashir-logo-on-light.png';

    public function __construct(private Application $app, private Repository $config) {}

    public function compose(View $view): void
    {
        $view->with('frame', $this->current());
    }

    private function current(): MailFrame
    {
        $locale = Locale::tryFrom($this->app->getLocale()) ?? Locale::Arabic;
        $isArabic = $locale === Locale::Arabic;

        return new MailFrame(
            $locale->value,
            $isArabic ? 'rtl' : 'ltr',
            $isArabic ? 'right' : 'left',
            $this->app->resourcePath(self::LOGO),
            FrontendUrl::to('privacy'),
            FrontendUrl::to('terms'),
            CarbonImmutable::now($this->config->string('app.display_timezone'))->year,
        );
    }
}
