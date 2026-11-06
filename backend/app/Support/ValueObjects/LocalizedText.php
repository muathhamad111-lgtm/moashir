<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\ScalarValue;
use InvalidArgumentException;
use JsonSerializable;

final readonly class LocalizedText implements JsonSerializable
{
    public function __construct(public string $ar, public string $en)
    {
        if (trim($ar) === '' || trim($en) === '') {
            throw new InvalidArgumentException('Localized text requires both Arabic and English values.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(ScalarValue::string($values['ar'] ?? null), ScalarValue::string($values['en'] ?? null));
    }

    public static function fromTranslation(string $key): self
    {
        return new self(ScalarValue::string(__($key, [], 'ar')), ScalarValue::string(__($key, [], 'en')));
    }

    /**
     * @param  array<string, self|string>  $replacements
     */
    public static function fromTranslationWith(string $key, array $replacements): self
    {
        return new self(
            ScalarValue::string(__($key, self::replacementsIn($replacements, 'ar'), 'ar')),
            ScalarValue::string(__($key, self::replacementsIn($replacements, 'en'), 'en')),
        );
    }

    public static function fromChoice(string $key, int $count): self
    {
        return new self(trans_choice($key, $count, [], 'ar'), trans_choice($key, $count, [], 'en'));
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
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function in(string $locale): string
    {
        return $locale === 'en' ? $this->en : $this->ar;
    }

    public function equals(self $other): bool
    {
        return $this->ar === $other->ar && $this->en === $other->en;
    }

    /**
     * @param  array<string, self|string>  $replacements
     * @return array<string, string>
     */
    private static function replacementsIn(array $replacements, string $locale): array
    {
        return array_map(static fn (self|string $value): string => $value instanceof self ? $value->in($locale) : $value, $replacements);
    }
}
