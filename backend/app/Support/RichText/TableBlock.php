<?php

declare(strict_types=1);

namespace App\Support\RichText;

use App\Enums\Cms\RichTextBlockType;

final readonly class TableBlock implements RichTextBlock
{
    private const string SEPARATOR_ROW = '/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?$/';

    /**
     * @param  list<string>|null  $header
     * @param  list<list<string>>  $rows
     */
    public function __construct(public ?array $header, public array $rows) {}

    /**
     * @param  list<string>  $lines
     */
    public static function fromLines(array $lines): self
    {
        $hasHeader = count($lines) > 1 && preg_match(self::SEPARATOR_ROW, $lines[1]) === 1;
        $bodyLines = array_values(array_filter(
            $hasHeader ? array_slice($lines, 2) : $lines,
            static fn (string $line): bool => preg_match(self::SEPARATOR_ROW, $line) !== 1,
        ));
        $header = $hasHeader ? self::cellsOf($lines[0]) : null;
        $rows = array_map(self::cellsOf(...), $bodyLines);
        $width = max([count($header ?? []), ...array_map(count(...), $rows)]);

        return new self(
            $header === null ? null : self::padded($header, $width),
            array_map(static fn (array $row): array => self::padded($row, $width), $rows),
        );
    }

    /**
     * @return array{type: string, header: list<string>|null, rows: list<list<string>>}
     */
    public function toArray(): array
    {
        return ['type' => RichTextBlockType::Table->value, 'header' => $this->header, 'rows' => $this->rows];
    }

    /**
     * @return list<string>
     */
    private static function cellsOf(string $line): array
    {
        $inner = preg_replace('/^\||\|$/', '', trim($line)) ?? '';

        return array_map(trim(...), explode('|', $inner));
    }

    /**
     * @param  list<string>  $cells
     * @return list<string>
     */
    private static function padded(array $cells, int $width): array
    {
        return array_pad($cells, $width, '');
    }
}
