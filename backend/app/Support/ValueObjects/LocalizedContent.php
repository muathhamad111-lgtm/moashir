<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final readonly class LocalizedContent implements JsonSerializable
{
    public string $ar;

    public string $en;

    public function __construct(?string $ar, ?string $en)
    {
        $this->ar = trim((string) $ar);
        $this->en = trim((string) $en);

        if ($this->ar === '' && $this->en === '') {
            throw new InvalidArgumentException('Localized content needs an Arabic or an English value.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function tryFromArray(array $values): ?self
    {
        $ar = is_string($values['ar'] ?? null) ? trim($values['ar']) : '';
        $en = is_string($values['en'] ?? null) ? trim($values['en']) : '';

        return $ar === '' && $en === '' ? null : new self($ar, $en);
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function toArray(): array
    {
        return ['ar' => $this->ar, 'en' => $this->en];
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function withFallback(): array
    {
        return [
            'ar' => $this->ar !== '' ? $this->ar : $this->en,
            'en' => $this->en !== '' ? $this->en : $this->ar,
        ];
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function in(string $locale): string
    {
        return $this->withFallback()[$locale === 'en' ? 'en' : 'ar'];
    }
}
