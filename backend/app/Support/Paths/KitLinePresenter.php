<?php

declare(strict_types=1);

namespace App\Support\Paths;

final class KitLinePresenter
{
    /**
     * @return array{status: null, request_id: null}
     */
    public static function placeholder(): array
    {
        return ['status' => null, 'request_id' => null];
    }
}
