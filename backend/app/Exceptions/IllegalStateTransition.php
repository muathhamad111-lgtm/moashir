<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class IllegalStateTransition extends ApiException
{
    public static function from(string $currentState, string $attemptedVerb): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'state.transition_illegal', [
            'state' => [$currentState],
            'attempted' => [$attemptedVerb],
        ]));
    }
}
