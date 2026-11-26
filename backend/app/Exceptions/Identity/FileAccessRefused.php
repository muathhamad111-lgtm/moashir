<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Enums\Identity\NationalIdState;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class FileAccessRefused extends ApiException
{
    public static function nationalIdRequired(NationalIdState $state): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'national_id.required_for_files', meta: ['national_id_status' => $state->value]));
    }
}
