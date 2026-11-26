<?php

declare(strict_types=1);

namespace App\Support\Cms\Meta;

use App\Rules\FreeOfHtmlMarkup;
use App\Support\RichText\RichTextSanitizer;

final readonly class LocalizedMetaField implements MetaField
{
    public const int LABEL_MAX = 120;

    public const int NOTE_MAX = 1000;

    private const array LOCALES = ['ar', 'en'];

    public function __construct(private string $key, private int $max) {}

    public static function label(string $key): self
    {
        return new self($key, self::LABEL_MAX);
    }

    public static function note(string $key): self
    {
        return new self($key, self::NOTE_MAX);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function rules(string $attribute): array
    {
        $rules = [$attribute => ['sometimes', 'required', 'array:'.implode(',', self::LOCALES)]];

        foreach (self::LOCALES as $locale) {
            $rules[$attribute.'.'.$locale] = ['required_with:'.$attribute, 'string', 'max:'.$this->max, new FreeOfHtmlMarkup];
        }

        return $rules;
    }

    public function attributeNames(string $attribute): array
    {
        $name = MetaFieldName::of($this->key);
        $names = [$attribute => $name];

        foreach (self::LOCALES as $locale) {
            $names[$attribute.'.'.$locale] = MetaFieldName::inLocale($name, $locale);
        }

        return $names;
    }

    public function clean(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach (self::LOCALES as $locale) {
            if (is_string($value[$locale] ?? null)) {
                $value[$locale] = RichTextSanitizer::clean($value[$locale]);
            }
        }

        return $value;
    }
}
