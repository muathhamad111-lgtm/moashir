<?php

declare(strict_types=1);

namespace App\Exceptions\Award;

use App\Data\Award\CycleOpenBlock;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;
use LogicException;

final class CycleStartBlocked extends ApiException
{
    private ?CycleOpenBlock $block = null;

    public static function by(CycleOpenBlock $block): self
    {
        $exception = new self(new ApiError(Response::HTTP_CONFLICT, $block->code(), [], [], [
            'award_cycle_id' => $block->blockingCycle->id,
            'concurrency' => $block->concurrency->value,
        ]));
        $exception->block = $block;

        return $exception;
    }

    public function block(): CycleOpenBlock
    {
        return $this->block ?? throw new LogicException('A blocked cycle start always carries its block.');
    }
}
