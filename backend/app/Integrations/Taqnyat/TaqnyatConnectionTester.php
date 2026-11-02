<?php

declare(strict_types=1);

namespace App\Integrations\Taqnyat;

use App\Contracts\Integrations\IntegrationConnectionTester;
use App\Data\Integrations\ConnectionCheck;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsSender;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\ProviderHttp;

final readonly class TaqnyatConnectionTester implements IntegrationConnectionTester
{
    public function __construct(private TaqnyatClient $client, private TaqnyatAccount $account) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Taqnyat;
    }

    public function test(ResolvedCredentials $credentials): ConnectionTestResult
    {
        $startedAt = (int) hrtime(true);

        if (! $this->client->status()->successful()) {
            return $this->result($credentials, $startedAt, [ConnectionCheck::failed('service_status', ProviderErrorKind::Unavailable->value)], [], ProviderErrorKind::Unavailable);
        }

        $checks = [ConnectionCheck::passed('service_status')];

        try {
            $balance = $this->account->balance($credentials);
            $checks[] = ConnectionCheck::passed('credentials_valid');
            $info = $balance->toInfo();
            $accountError = match (true) {
                $balance->isAccountInactive() => ProviderErrorKind::AccountInactive,
                $balance->isEmpty() => ProviderErrorKind::QuotaExhausted,
                default => null,
            };

            if ($accountError instanceof ProviderErrorKind) {
                $checks[] = ConnectionCheck::failed('account_usable', $accountError->value);

                return $this->result($credentials, $startedAt, $checks, $info, $accountError);
            }

            $checks[] = ConnectionCheck::passed('account_usable');
            $checks[] = $this->senderCheck($credentials);
        } catch (ProviderCallFailed $failure) {
            $checks[] = ConnectionCheck::failed(count($checks) === 1 ? 'credentials_valid' : 'sender_active', $failure->kind->value);

            return $this->result($credentials, $startedAt, $checks, $info ?? [], $failure->kind);
        }

        $senderCheck = end($checks);

        return $this->result($credentials, $startedAt, $checks, $info, $senderCheck->ok ? null : ProviderErrorKind::Misconfigured);
    }

    private function senderCheck(ResolvedCredentials $credentials): ConnectionCheck
    {
        $configured = $credentials->string('sender_name');
        $active = array_filter(
            $this->account->senders($credentials),
            static fn (SmsSender $sender): bool => $sender->name === $configured && $sender->isActive(),
        );

        return $active === [] ? ConnectionCheck::failed('sender_active', 'sender_not_active') : ConnectionCheck::passed('sender_active');
    }

    /**
     * @param  list<ConnectionCheck>  $checks
     * @param  array<string, scalar|null>  $info
     */
    private function result(ResolvedCredentials $credentials, int $startedAt, array $checks, array $info, ?ProviderErrorKind $error = null): ConnectionTestResult
    {
        return new ConnectionTestResult($this->provider(), $credentials->mode, ProviderHttp::elapsedMilliseconds($startedAt), $checks, $info, $error);
    }
}
