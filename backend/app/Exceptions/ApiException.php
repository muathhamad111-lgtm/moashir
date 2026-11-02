<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ApiError;
use RuntimeException;

abstract class ApiException extends RuntimeException
{
    final protected function __construct(private readonly ApiError $error)
    {
        parent::__construct($error->code);
    }

    public function error(): ApiError
    {
        return $this->error;
    }
}
