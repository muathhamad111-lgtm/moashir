<?php

declare(strict_types=1);

namespace App\Exceptions\Settings;

use App\Data\Settings\ContrastCheck;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class BrandingRejected extends ApiException
{
    /**
     * @param  list<ContrastCheck>  $failures
     */
    public static function contrastFail(array $failures): self
    {
        $fields = [];

        foreach ($failures as $check) {
            $fields[$check->field()] = [__('errors.contrast_fail')];
        }

        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'contrast_fail', $fields, [], [
            'pairs' => array_map(static fn (ContrastCheck $check): array => $check->toArray(), $failures),
        ]));
    }
}
