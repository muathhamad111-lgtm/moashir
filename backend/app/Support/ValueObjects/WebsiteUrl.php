<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class WebsiteUrl
{
    public const int MAX_LENGTH = 255;

    private const string SCHEME = 'https';

    private const int PORT = 443;

    private const string UNSAFE_CHARACTERS = '/[\s<>"\'`\\\\{}|^\x00-\x1F\x7F]/';

    private const string HOST = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/';

    public string $value;

    public function __construct(string $url)
    {
        $this->value = self::normalise($url) ?? throw new InvalidArgumentException('A website is a public https:// address.');
    }

    public static function tryFrom(string $url): ?self
    {
        return self::normalise($url) === null ? null : new self($url);
    }

    private static function normalise(string $url): ?string
    {
        $parts = self::partsOf(trim($url));

        if ($parts === null || ! self::isPublicHttps($parts)) {
            return null;
        }

        $normalised = self::compose($parts);

        return strlen($normalised) <= self::MAX_LENGTH && filter_var($normalised, FILTER_VALIDATE_URL) !== false ? $normalised : null;
    }

    /**
     * @return array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}|null
     */
    private static function partsOf(string $url): ?array
    {
        if ($url === '' || preg_match(self::UNSAFE_CHARACTERS, $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        return is_array($parts) ? $parts : null;
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}  $parts
     */
    private static function isPublicHttps(array $parts): bool
    {
        return strtolower($parts['scheme'] ?? '') === self::SCHEME
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ($parts['port'] ?? self::PORT) === self::PORT
            && preg_match(self::HOST, strtolower($parts['host'] ?? '')) === 1;
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}  $parts
     */
    private static function compose(array $parts): string
    {
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        if ($path === '/' && $query === '') {
            $path = '';
        }

        return self::SCHEME.'://'.strtolower($parts['host'] ?? '').$path.$query;
    }
}
