<?php

declare(strict_types=1);

namespace App\Support\Certificates;

use App\Support\FrontendUrl;

final class CertificateLinks
{
    public const string ENTITY_CERTIFICATES = '/certificates';

    public static function verification(string $code): string
    {
        return FrontendUrl::to('verify/'.$code);
    }
}
