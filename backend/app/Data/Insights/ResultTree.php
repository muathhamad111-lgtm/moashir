<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Support\ValueObjects\LocalizedText;

final readonly class ResultTree
{
    /**
     * @param  list<ResultMain>  $mains
     */
    public function __construct(
        public int $criteriaVersionId,
        public ?LocalizedText $versionLabel,
        public ResultFigures $total,
        public array $mains,
    ) {}

    public function main(string $code): ?ResultMain
    {
        return array_find($this->mains, static fn (ResultMain $main): bool => $main->code === $code);
    }
}
