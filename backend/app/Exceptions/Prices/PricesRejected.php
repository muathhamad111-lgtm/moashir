<?php

declare(strict_types=1);

namespace App\Exceptions\Prices;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class PricesRejected extends ApiException
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function staleVersion(array $row): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'prices.stale_version', [], [], ['row' => $row]));
    }

    public static function noScheduledVersion(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'prices.no_scheduled_version'));
    }
}
