<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Integrations\IntegrationKind;
use App\Exceptions\Console\MailSamplesRefused;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Integrations\IntegrationCredentialsResolver;
use App\Services\Integrations\IntegrationRows;
use Illuminate\Contracts\Foundation\Application;

/**
 * Sample e-mails carry fictional data that must never reach real recipients from production.
 */
final readonly class MailSampleDeliveryGuard
{
    private const array ALLOWED_ENVIRONMENTS = ['staging', 'local'];

    public function __construct(
        private Application $app,
        private IntegrationRows $rows,
        private IntegrationCredentialsResolver $resolver,
    ) {}

    public function connection(): IntegrationCredential
    {
        if ($this->app->isProduction()) {
            throw MailSamplesRefused::inProduction();
        }

        if (! $this->app->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw MailSamplesRefused::environmentNotAllowed($this->app->environment());
        }

        $connection = $this->rows->active(IntegrationKind::Email);

        if (! $connection instanceof IntegrationCredential || ! $this->resolver->fromRow($connection)->isConfigured()) {
            throw MailSamplesRefused::smtpNotConfigured();
        }

        return $connection;
    }
}
