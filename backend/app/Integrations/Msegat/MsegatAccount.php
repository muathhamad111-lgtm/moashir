<?php

declare(strict_types=1);

namespace App\Integrations\Msegat;

use App\Contracts\Integrations\SmsAccountInspector;
use App\Contracts\Integrations\SmsTemplateDirectory;
use App\Data\Integrations\ProviderSmsTemplate;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsBalance;
use App\Enums\Integrations\IntegrationProvider;
use App\Support\ScalarValue;
use Illuminate\Http\Client\Response;

final readonly class MsegatAccount implements SmsAccountInspector, SmsTemplateDirectory
{
    private const array NO_TEMPLATE_CODES = ['M0092', 'M0041'];

    public function __construct(private MsegatClient $client) {}

    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Msegat;
    }

    public function balance(ResolvedCredentials $credentials): SmsBalance
    {
        $response = $this->client->postForm($credentials, 'account_balance', 'Credits.php', ['msgEncoding' => 'UTF8']);
        $balance = $this->balanceIn($response);

        if (! $response->successful() || $balance === null) {
            throw MsegatResponse::failure($response, 'account_balance');
        }

        return new SmsBalance($balance);
    }

    public function templates(ResolvedCredentials $credentials): array
    {
        $response = $this->client->postJson($credentials, 'list_templates', 'GetMessageTemplates.php');
        $code = MsegatResponse::code($response);

        if (in_array($code, self::NO_TEMPLATE_CODES, true)) {
            return [];
        }

        if (! $response->successful() || ! MsegatResponse::isSuccess($code)) {
            throw MsegatResponse::failure($response, 'list_templates');
        }

        $result = $response->json('result');
        $templates = [];

        foreach (is_array($result) ? $result : [] as $template) {
            if (is_array($template) && is_string($template['msgBody'] ?? null)) {
                $templates[] = new ProviderSmsTemplate(ScalarValue::string($template['id'] ?? null), ScalarValue::string($template['title'] ?? null), $template['msgBody']);
            }
        }

        return $templates;
    }

    private function balanceIn(Response $response): ?string
    {
        $json = $response->json('userBalance');

        if (is_numeric($json)) {
            return (string) $json;
        }

        $body = trim($response->body());

        if (! is_numeric($body) || MsegatResponse::isCredentialError($body)) {
            return null;
        }

        return $body;
    }
}
