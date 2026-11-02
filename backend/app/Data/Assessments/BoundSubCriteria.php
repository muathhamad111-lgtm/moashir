<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class BoundSubCriteria
{
    /**
     * @var array<string, BoundSub>
     */
    private array $byCode;

    /**
     * @param  list<BoundSub>  $subs
     */
    public function __construct(public array $subs)
    {
        $byCode = [];

        foreach ($subs as $sub) {
            $byCode[$sub->code] = $sub;
        }

        $this->byCode = $byCode;
    }

    public function withId(int $id): ?BoundSub
    {
        foreach ($this->subs as $sub) {
            if ($sub->id === $id) {
                return $sub;
            }
        }

        return null;
    }

    public function withCode(string $code): ?BoundSub
    {
        return $this->byCode[$code] ?? null;
    }

    public function idOf(string $code): ?int
    {
        return $this->withCode($code)?->id;
    }
}
