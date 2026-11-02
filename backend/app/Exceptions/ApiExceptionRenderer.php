<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Integrations\ProviderCallFailed;
use App\Http\Responses\ApiError;
use App\Http\Responses\ApiResponse;
use App\Support\ScalarValue;
use App\Support\StringList;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ApiExceptionRenderer
{
    private const array HTTP_STATUS_CODES = [
        Response::HTTP_BAD_REQUEST => 'request.malformed',
        Response::HTTP_UNAUTHORIZED => 'unauthenticated',
        Response::HTTP_FORBIDDEN => 'forbidden',
        Response::HTTP_NOT_FOUND => 'not_found',
        Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
        Response::HTTP_NOT_ACCEPTABLE => 'request.not_acceptable',
        Response::HTTP_REQUEST_ENTITY_TOO_LARGE => 'request.too_large',
        419 => 'csrf.token_mismatch',
        Response::HTTP_SERVICE_UNAVAILABLE => 'service_unavailable',
    ];

    public function __construct(private Repository $config) {}

    public function render(Throwable $exception): ?JsonResponse
    {
        $error = $this->toApiError($exception);

        return $error instanceof ApiError ? ApiResponse::error($error) : null;
    }

    private function toApiError(Throwable $exception): ?ApiError
    {
        return match (true) {
            $exception instanceof HttpResponseException => null,
            $exception instanceof ApiException => $exception->error(),
            $exception instanceof ProviderCallFailed => new ApiError(Response::HTTP_SERVICE_UNAVAILABLE, 'provider_unavailable'),
            $exception instanceof ValidationException => new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'validation_failed', $this->fieldErrors($exception)),
            $exception instanceof AuthenticationException => new ApiError(Response::HTTP_UNAUTHORIZED, 'unauthenticated'),
            $exception instanceof ThrottleRequestsException => new ApiError(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited', [], $this->stringHeaders($exception)),
            $exception instanceof HttpExceptionInterface => $this->fromHttpException($exception),
            default => $this->fromUnexpected(),
        };
    }

    private function fromHttpException(HttpExceptionInterface $exception): ApiError
    {
        $status = $exception->getStatusCode();

        return new ApiError($status, self::HTTP_STATUS_CODES[$status] ?? 'http_error', [], $this->stringHeaders($exception));
    }

    private function fromUnexpected(): ?ApiError
    {
        if ($this->config->get('app.debug') === true) {
            return null;
        }

        return new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'server_error');
    }

    /**
     * @return array<string, list<string>>
     */
    private function fieldErrors(ValidationException $exception): array
    {
        $fields = [];

        foreach ($exception->errors() as $field => $messages) {
            $fields[(string) $field] = StringList::from($messages);
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    private function stringHeaders(HttpExceptionInterface $exception): array
    {
        $headers = [];

        foreach ($exception->getHeaders() as $name => $value) {
            $headers[(string) $name] = ScalarValue::string($value);
        }

        return $headers;
    }
}
