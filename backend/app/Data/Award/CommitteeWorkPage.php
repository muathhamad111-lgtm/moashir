<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\CommitteeEntityVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 */
final readonly class CommitteeWorkPage
{
    /**
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  list<int>  $memberIds
     */
    public function __construct(public LengthAwarePaginator $paginator, public CommitteeEntityVisibility $visibility, public array $memberIds) {}
}
