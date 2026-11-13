<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use App\Support\Security\SensitiveUrl;
use Sentry\Breadcrumb;
use Sentry\Event;

final class SentryEventScrubber
{
    private const string REDACTED = '[redacted]';

    private const array PATTERNS = [
        '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
        '/\+?9665\d{8}\b/',
        '/\b05\d{8}\b/',
        '/\b[12]\d{9}\b/',
        '/[\w\-. ]+\.(pdf|docx?|xlsx?|pptx?|jpe?g|png)\b/i',
    ];

    private const string CREDENTIAL_FIELDS = '/(["\']?(?:apiKey|api_key|userName|secret_token|shared_secret|secret_key|webhook_secret|bearer_token|client_secret|x-api-key|authorization|password)["\']?\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,&}]+)/i';

    private const array CREDENTIAL_PATTERNS = [
        '/\bBearer\s+[A-Za-z0-9._~+\/=\-]+/i',
        '/\b(?:sk|pk)_(?:test|live)_[A-Za-z0-9]+/',
        '/\bsk-[A-Za-z0-9_\-]{16,}/',
    ];

    private const array SAFE_HEADERS = ['accept', 'accept-language', 'content-type', 'x-request-id', 'user-agent'];

    private const array DROPPED_BREADCRUMB_METADATA = ['http.query', 'http.fragment', 'http.request.body', 'http.response.body'];

    public static function scrub(Event $event): Event
    {
        $event->setUser(null);
        $event->setRequest(self::scrubRequest($event->getRequest()));
        $event->setExtra([]);

        $message = $event->getMessage();

        if ($message !== null) {
            $event->setMessage(self::scrubText($message));
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::scrubText($exception->getValue()));
        }

        $event->setBreadcrumb(array_map(self::scrubBreadcrumb(...), $event->getBreadcrumbs()));

        return $event;
    }

    public static function scrubText(string $text): string
    {
        $withoutCredentials = (string) preg_replace(self::CREDENTIAL_PATTERNS, self::REDACTED, SensitiveUrl::redact($text));
        $withoutCredentials = (string) preg_replace(self::CREDENTIAL_FIELDS, '$1'.self::REDACTED, $withoutCredentials);

        return (string) preg_replace(self::PATTERNS, self::REDACTED, $withoutCredentials);
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private static function scrubRequest(array $request): array
    {
        $headers = is_array($request['headers'] ?? null) ? $request['headers'] : [];

        return [
            'method' => $request['method'] ?? null,
            'url' => is_string($request['url'] ?? null) ? SensitiveUrl::redact((string) strtok($request['url'], '?')) : null,
            'headers' => array_intersect_key($headers, array_flip(self::SAFE_HEADERS)),
        ];
    }

    private static function scrubBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $message = $breadcrumb->getMessage();
        $scrubbed = $message === null ? $breadcrumb : $breadcrumb->withMessage(self::scrubText($message));

        foreach ($scrubbed->getMetadata() as $name => $value) {
            $scrubbed = match (true) {
                in_array($name, self::DROPPED_BREADCRUMB_METADATA, true) => $scrubbed->withoutMetadata($name),
                is_string($value) => $scrubbed->withMetadata($name, self::scrubText(strtok($value, '?') ?: '')),
                default => $scrubbed,
            };
        }

        return $scrubbed;
    }
}
