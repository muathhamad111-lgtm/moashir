<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Security\SensitiveUrl;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

final class UrlWithoutQueryResolver implements Resolver
{
    public static function resolve(Auditable $auditable): string
    {
        return App::runningInConsole() && ! App::runningUnitTests() ? 'console' : SensitiveUrl::redact(Request::url());
    }
}
