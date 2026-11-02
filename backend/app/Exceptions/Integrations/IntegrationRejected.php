<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use App\Data\Integrations\ConnectionTestResult;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\ProviderErrorKind;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class IntegrationRejected extends ApiException
{
    public static function providerError(ProviderErrorKind $kind, ?ConnectionTestResult $result = null): self
    {
        return new self(new ApiError(
            Response::HTTP_BAD_GATEWAY,
            'provider_error',
            ['provider_error_kind' => [$kind->value]],
            [],
            $result instanceof ConnectionTestResult ? ['result' => $result->toArray()] : [],
        ));
    }

    public static function notConfigured(IntegrationKind $kind): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'integration.not_configured', [], [], ['kind' => $kind->value]));
    }

    public static function testRequired(string $field): self
    {
        return self::fieldError('integration.test_required', $field);
    }

    public static function configurationIncomplete(string $field): self
    {
        return self::fieldError('integration.configuration_incomplete', $field);
    }

    public static function reasonRequired(): self
    {
        return self::fieldError('reason_required', 'reason');
    }

    public static function acknowledgementRequired(string $field): self
    {
        return self::fieldError('ai.data_transfer_acknowledgement_required', $field);
    }

    public static function methodRequiresConfirmation(): self
    {
        return self::fieldError('payment.method_requires_confirmation', 'methods');
    }

    public static function confirmationInUse(string $field): self
    {
        return self::fieldError('payment.confirmation_in_use', $field);
    }

    public static function webhookRequiresHttps(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'payment.webhook_requires_https'));
    }

    public static function recipientNotAllowedInTestMode(): self
    {
        return self::fieldError('sms.recipient_not_in_test_numbers', 'recipient');
    }

    public static function templateNotApproved(string $field): self
    {
        return self::fieldError('sms.template_not_approved', $field);
    }

    public static function templatesNotLoaded(): self
    {
        return self::fieldError('sms.templates_not_loaded', 'templates');
    }

    public static function hostNotAllowed(string $field): self
    {
        return self::fieldError('email.host_not_allowed', $field);
    }

    public static function testAddressRequired(): self
    {
        return self::fieldError('email.test_address_required', 'test_address');
    }

    public static function associationFileMissing(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'payment.apple_pay_file_missing'));
    }

    private static function fieldError(string $code, string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [$field => [__('errors.'.$code)]]));
    }
}
