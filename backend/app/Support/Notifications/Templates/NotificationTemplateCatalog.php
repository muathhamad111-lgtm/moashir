<?php

declare(strict_types=1);

namespace App\Support\Notifications\Templates;

use App\Enums\Notifications\NotificationCode;
use App\Support\ScalarValue;

final readonly class NotificationTemplateCatalog
{
    /**
     * @param  list<NotificationTextTemplate>  $templates
     */
    private function __construct(private array $templates) {}

    /**
     * @param  array<array-key, mixed>  $arabic  the `notifications` translation group in Arabic
     * @param  array<array-key, mixed>  $english  the same group in English
     */
    public static function fromLines(array $arabic, array $english): self
    {
        $leaves = self::leaves($english);
        $grouped = [];

        foreach ($leaves as $key => $text) {
            $code = NotificationTextKeys::codeOf($key);

            if (! $code instanceof NotificationCode) {
                continue;
            }

            [$parent, $part] = self::split($key);
            $grouped[$parent]['code'] = $code;
            $grouped[$parent]['parts'][$part] = ['ar' => ScalarValue::string(data_get($arabic, $key), $text), 'en' => $text];
        }

        return new self(array_map(self::template(...), array_keys($grouped), $grouped));
    }

    /**
     * @return list<NotificationTextTemplate>
     */
    public function all(): array
    {
        return $this->templates;
    }

    /**
     * @return list<NotificationTextTemplate>
     */
    public function of(NotificationCode $code): array
    {
        return array_values(array_filter($this->templates, static fn (NotificationTextTemplate $template): bool => $template->code === $code));
    }

    public function find(string $key): ?NotificationTextTemplate
    {
        foreach ($this->templates as $template) {
            if ($template->key === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param  array{code: NotificationCode, parts: array<string, array{ar: string, en: string}>}  $group
     */
    private static function template(string $key, array $group): NotificationTextTemplate
    {
        $placeholders = [];

        foreach ($group['parts'] as $text) {
            $placeholders = [...$placeholders, ...TemplatePlaceholders::in($text['ar']), ...TemplatePlaceholders::in($text['en'])];
        }

        return new NotificationTextTemplate($key, $group['code'], $group['parts'], array_values(array_unique($placeholders)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function split(string $key): array
    {
        $position = (int) strrpos($key, '.');

        return [substr($key, 0, $position), substr($key, $position + 1)];
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return array<string, string>
     */
    private static function leaves(array $lines, string $prefix = ''): array
    {
        $leaves = [];

        foreach ($lines as $name => $value) {
            $key = $prefix === '' ? (string) $name : $prefix.'.'.$name;
            $leaves = is_array($value) ? [...$leaves, ...self::leaves($value, $key)] : [...$leaves, $key => ScalarValue::string($value)];
        }

        return $leaves;
    }
}
