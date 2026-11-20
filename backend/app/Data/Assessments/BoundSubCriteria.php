<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Contracts\Assessments\EvidenceNode;
use App\Support\ValueObjects\LocalizedText;

final readonly class BoundSubCriteria
{
    /**
     * @var array<string, BoundSub>
     */
    private array $byCode;

    /**
     * @var array<string, BoundMain>
     */
    private array $mainsByCode;

    /**
     * @param  list<BoundSub>  $subs
     * @param  list<BoundMain>  $mains  the active main criteria, for their own attachments
     */
    public function __construct(public array $subs, public array $mains = [])
    {
        $this->byCode = array_column(array_map(static fn (BoundSub $sub): array => [$sub->code, $sub], $subs), 1, 0);
        $this->mainsByCode = array_column(array_map(static fn (BoundMain $main): array => [$main->code, $main], $mains), 1, 0);
    }

    public function withId(int $id): ?BoundSub
    {
        return array_find($this->subs, static fn (BoundSub $sub): bool => $sub->id === $id);
    }

    public function withCode(string $code): ?BoundSub
    {
        return $this->byCode[$code] ?? null;
    }

    public function idOf(string $code): ?int
    {
        return $this->withCode($code)?->id;
    }

    public function mainWithId(int $id): ?BoundMain
    {
        return array_find($this->mains, static fn (BoundMain $main): bool => $main->id === $id);
    }

    public function mainWithCode(string $code): ?BoundMain
    {
        return $this->mainsByCode[$code] ?? null;
    }

    /**
     * The main criterion or sub-criterion holding the code; codes are unique across the levels of a version.
     */
    public function nodeWithCode(string $code): ?EvidenceNode
    {
        return $this->withCode($code) ?? $this->mainWithCode($code);
    }

    public function nameOf(string $code): ?LocalizedText
    {
        return $this->nodeWithCode($code)?->nodeName();
    }
}
