<?php

declare(strict_types=1);

namespace App\Http\Controllers\WellKnown;

use App\Services\Integrations\ApplePayAssociationFile;
use Illuminate\Http\Response;

final class ApplePayDomainAssociationController
{
    public function __invoke(ApplePayAssociationFile $file): Response
    {
        $contents = $file->contents();

        abort_if($contents === null, Response::HTTP_NOT_FOUND);

        return new Response($contents, Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
