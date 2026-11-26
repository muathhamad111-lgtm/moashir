<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A list's sort from its validated `sort` query value: a column, "-" in front for descending.
 */
final readonly class ListOrder
{
    private const string DESCENDING = '-';

    private function __construct(public string $column, public bool $descending) {}

    public static function fromQuery(string $sort, string $defaultColumn): self
    {
        if ($sort === '') {
            return new self($defaultColumn, false);
        }

        return new self(ltrim($sort, self::DESCENDING), str_starts_with($sort, self::DESCENDING));
    }

    /**
     * Every accepted `sort` value for these columns, ascending and descending.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function queryValues(array $columns): array
    {
        return [...$columns, ...array_map(static fn (string $column): string => self::DESCENDING.$column, $columns)];
    }

    /**
     * @return 'asc'|'desc'
     */
    public function direction(): string
    {
        return $this->descending ? 'desc' : 'asc';
    }
}
