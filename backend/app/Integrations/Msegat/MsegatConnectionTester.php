<?php

declare(strict_types=1);

namespace App\Integrations\Msegat;

use App\Contracts\Integrations\IntegrationConnectionTester;
use App\Data\Integrations\ConnectionCheck;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Services\Integrations\ProviderHttp;

final readonly class MsegatConnectionTester implements IntegrationConnectionTester
{
    public function __construct(private MsegatAccount $account) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Msegat;
    }

    public function test(ResolvedCredentials $credentials): ConnectionTestResult
    {
        $startedAt = (int) hrtime(true);

        try {
            $balance = $this->account->balance($credentials);
        } catch (ProviderCallFailed $failure) {
            return $this->result($credentials, $startedAt, [ConnectionCheck::failed('credentials_valid', $failure->kind->value)], [], $failure->kind);
        }

        $checks = [ConnectionCheck::passed('credentials_valid')];
        $info = ['balance' => $balance->amount, 'sender_verified_by' => 'test_sms'];

        if ($balance->isEmpty()) {
            $checks[] = ConnectionCheck::failed('account_usable', ProviderErrorKind::QuotaExhausted->value);

            return $this->result($credentials, $startedAt, $checks, $info, ProviderErrorKind::QuotaExhausted);
        }

        $checks[] = ConnectionCheck::passed('account_usable');

        return $this->result($credentials, $startedAt, $checks, $info);
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
