<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Contracts\Integrations\PaymentWebhookRegistrar;
use App\Data\Integrations\CredentialChanges;
use App\Data\Integrations\RegisteredWebhook;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Models\Identity\User;
use App\Services\Integrations\CredentialWriter;
use App\Services\Integrations\IntegrationConfigVersions;
use App\Services\Integrations\IntegrationProbe;
use App\Services\Integrations\IntegrationRows;
use App\Services\Integrations\ProviderRefsWriter;
use App\Services\Notifications\SecurityAlerter;
use App\Support\Integrations\IntegrationUrls;
use App\Support\RequestContext;
use App\Support\ValueObjects\SecretValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RegisterPaymentWebhook
{
    private const int SECRET_BYTES = 32;

    public function __construct(
        private IntegrationProbe $probe,
        private PaymentWebhookRegistrar $registrar,
        private IntegrationUrls $urls,
        private IntegrationRows $rows,
        private CredentialWriter $writer,
        private ProviderRefsWriter $refs,
        private IntegrationConfigVersions $versions,
        private SecurityAlerter $alerter,
        private RequestContext $context,
    ) {}

    public function handle(User $actor, IntegrationMode $mode, ?string $reason): RegisteredWebhook
    {
        $this->assertAllowed($actor, $reason);
        $credentials = $this->probe->configured(IntegrationProvider::Moyasar, $mode);
        $url = $this->urls->moyasarWebhook();
        $secret = new SecretValue(bin2hex(random_bytes(self::SECRET_BYTES)));

        try {
            foreach ($this->registrar->registeredFor($credentials, $url) as $existing) {
                $this->registrar->remove($credentials, $existing);
            }

            $webhook = $this->registrar->register($credentials, $url, $secret);
        } catch (ProviderCallFailed $failure) {
            throw IntegrationRejected::providerError($failure->kind);
        }

        $this->store($actor, $mode, $webhook, $secret);

        return $webhook;
    }

    private function assertAllowed(User $actor, ?string $reason): void
    {
        if (! $this->urls->isHttps()) {
            throw IntegrationRejected::webhookRequiresHttps();
        }

        if ($actor->isSuperAdmin()) {
            if ($reason === null || trim($reason) === '') {
                throw IntegrationRejected::reasonRequired();
            }

            $this->context->recordReason($reason);
        }
    }

    private function store(User $actor, IntegrationMode $mode, RegisteredWebhook $webhook, SecretValue $secret): void
    {
        DB::transaction(function () use ($actor, $mode, $webhook, $secret): void {
            $this->rows->lockKind(IntegrationKind::Payment);
            $row = $this->rows->findOrNew(IntegrationProvider::Moyasar, $mode);

            $this->writer->apply($row, new CredentialChanges([], ['webhook_secret' => $secret]), $actor);
            $this->refs->put($row, [
                'webhook_id' => $webhook->id,
                'webhook_url' => $webhook->url,
                'webhook_registered_at' => Date::now()->toIso8601String(),
            ], AuditEvent::PaymentWebhookRegistered);

            DB::afterCommit(function (): void {
                $this->versions->forget(IntegrationKind::Payment);
                $this->alerter->raise(NotificationCode::IntegrationSecretReplaced);
            });
        });
    }
}
