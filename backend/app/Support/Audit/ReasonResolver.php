<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\RequestContext;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

final class ReasonResolver implements Resolver
{
    public static function resolve(Auditable $auditable): ?string
    {
        return app(RequestContext::class)->reason();
    }
}
