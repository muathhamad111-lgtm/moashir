<?php

declare(strict_types=1);

namespace App\Support\Localization;

use App\Support\StringList;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;

final readonly class LocaleNegotiator
{
    public const string COOKIE = 'locale';

    public function __construct(private Repository $config) {}

    public function forGuest(Request $request): string
    {
        return $this->fromHeader($request) ?? $this->fromCookie($request) ?? $this->config->string('app.fallback_locale');
    }

    public function fromHeader(Request $request): ?string
    {
        $supported = $this->supportedLocales();

        foreach (AcceptHeader::fromString($request->headers->get('Accept-Language'))->all() as $item) {
            $language = $this->primarySubtag($item->getValue());

            if ($item->getQuality() > 0 && in_array($language, $supported, true)) {
                return $language;
            }
        }

        return null;
    }

    private function fromCookie(Request $request): ?string
    {
        $cookie = $request->cookies->get(self::COOKIE);

        return is_string($cookie) && in_array($cookie, $this->supportedLocales(), true) ? $cookie : null;
    }

    private function primarySubtag(string $languageTag): string
    {
        return strtolower(explode('-', str_replace('_', '-', $languageTag), 2)[0]);
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        return StringList::from($this->config->get('app.supported_locales'));
    }
}
