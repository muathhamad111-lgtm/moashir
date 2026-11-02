<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\ScalarValue;
use InvalidArgumentException;
use JsonSerializable;

final readonly class EntityName implements JsonSerializable
{
    public string $ar;

    public ?string $en;

    public function __construct(string $ar, ?string $en = null)
    {
        $arabic = trim($ar);
        $english = $en === null ? null : trim($en);

        if ($arabic === '') {
            throw new InvalidArgumentException('An entity name requires an Arabic value.');
        }

        $this->ar = $arabic;
        $this->en = $english === '' ? null : $english;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $english = $values['en'] ?? null;

        return new self(ScalarValue::string($values['ar'] ?? null), is_string($english) ? $english : null);
    }

    public static function pseudonym(string $code): self
    {
        return new self($code, $code);
    }

    /**
     * @return array{ar: string, en: string|null}
     */
    public function toArray(): array
    {
        return ['ar' => $this->ar, 'en' => $this->en];
    }

    /**
     * @return array{ar: string, en: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toLocalizedText(): LocalizedText
    {
        return new LocalizedText($this->ar, $this->en ?? $this->ar);
    }

    public function in(string $locale): string
    {
        return $locale === 'en' && $this->en !== null ? $this->en : $this->ar;
    }

    public function equals(self $other): bool
    {
        return $this->ar === $other->ar && $this->en === $other->en;
    }
}
