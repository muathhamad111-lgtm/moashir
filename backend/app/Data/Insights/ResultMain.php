<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Support\ValueObjects\LocalizedText;

final readonly class ResultMain
{
    /**
     * @param  list<ResultSub>  $subs
     */
    public function __construct(
        public string $code,
        public ?LocalizedText $name,
        public ?LocalizedText $description,
        public ResultFigures $figures,
        public int $position,
        public array $subs,
    ) {}

    public function sub(string $code): ?ResultSub
    {
        return array_find($this->subs, static fn (ResultSub $sub): bool => $sub->code === $code);
    }
}
