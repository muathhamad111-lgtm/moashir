<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Data\Assessments\ReviewProgress;
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
     * @param  array<int, ReviewProgress>  $progress  scoring progress by assignment id
     */
    public function __construct(public LengthAwarePaginator $paginator, public CommitteeEntityVisibility $visibility, public array $memberIds, public array $progress = []) {}
}
