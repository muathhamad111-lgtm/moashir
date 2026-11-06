<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Enums\Settings\PathEntityText;

final readonly class PathEntityTexts
{
    /**
     * @param  array<value-of<PathEntityText>, LocalizedText|null>  $texts
     */
    private function __construct(private array $texts) {}

    public static function empty(): self
    {
        return self::fromStored([]);
    }

    public static function fromStored(mixed $stored): self
    {
        $values = is_array($stored) ? $stored : [];
        $texts = [];

        foreach (PathEntityText::cases() as $key) {
            $text = $values[$key->value] ?? null;
            $texts[$key->value] = is_array($text) && self::isComplete($text) ? LocalizedText::fromArray($text) : null;
        }

        return new self($texts);
    }

    /**
     * @param  array<value-of<PathEntityText>, LocalizedText|null>  $changes
     */
    public function replacing(array $changes): self
    {
        return new self(array_replace($this->texts, array_intersect_key($changes, $this->texts)));
    }

    public function of(PathEntityText $key): ?LocalizedText
    {
        return $this->texts[$key->value] ?? null;
    }

    /**
     * @return array<value-of<PathEntityText>, array{ar: string, en: string}|null>
     */
    public function toArray(): array
    {
        return array_map(static fn (?LocalizedText $text): ?array => $text?->toArray(), $this->texts);
    }

    /**
     * @param  array<array-key, mixed>  $text
     */
    private static function isComplete(array $text): bool
    {
        return is_string($text['ar'] ?? null) && trim($text['ar']) !== '' && is_string($text['en'] ?? null) && trim($text['en']) !== '';
    }
}
