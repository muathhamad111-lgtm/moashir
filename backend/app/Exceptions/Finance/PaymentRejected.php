<?php

declare(strict_types=1);

namespace App\Exceptions\Finance;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class PaymentRejected extends ApiException
{
    public static function idempotencyKeyMissing(): self
    {
        return self::fieldError('idempotency_key.missing', 'idempotency_key');
    }

    public static function idempotencyKeyReused(): self
    {
        return self::fieldError('idempotency_key.reused', 'idempotency_key');
    }

    public static function purposeNotAvailable(): self
    {
        return self::fieldError('purpose.not_available', 'purpose');
    }

    public static function priceNotConfigured(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'payment.price_not_configured'));
    }

    public static function gatewayNotConfigured(): self
    {
        return new self(new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'integration.not_configured', [], [], ['kind' => 'payment']));
    }

    public static function entityRequired(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'payment.entity_required'));
    }

    public static function entityNotActive(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'payment.entity_not_active'));
    }

    public static function checkoutInProgress(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'payment.checkout_in_progress'));
    }

    public static function bindingMismatch(): self
    {
        return self::fieldError('payment.binding_mismatch', 'provider_payment_id', Response::HTTP_CONFLICT);
    }

    public static function providerPaymentNotFound(): self
    {
        return self::fieldError('payment.provider_payment_not_found', 'provider_payment_id');
    }

    public static function verificationRejected(): self
    {
        return self::fieldError('payment.verification_rejected', 'provider_payment_id', Response::HTTP_CONFLICT);
    }

    public static function providerPaymentIdRequired(): self
    {
        return self::fieldError('payment.provider_payment_id_required', 'provider_payment_id');
    }

    public static function providerPaymentIdInUse(): self
    {
        return self::fieldError('payment.provider_payment_id_in_use', 'provider_payment_id');
    }

    private static function fieldError(string $code, string $field, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): self
    {
        return new self(new ApiError($status, $code, [$field => [__('errors.'.$code)]]));
    }
}
