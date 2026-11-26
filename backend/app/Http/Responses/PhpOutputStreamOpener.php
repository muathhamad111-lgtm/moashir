<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Contracts\Platform\DownloadStreamOpener;

final class PhpOutputStreamOpener implements DownloadStreamOpener
{
    /**
     * @return resource|false
     */
    public function open(): mixed
    {
        return fopen('php://output', 'wb');
    }
}
