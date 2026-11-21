<?php

declare(strict_types=1);

namespace App\Contracts\Platform;

interface DownloadStreamOpener
{
    /**
     * @return resource|false
     */
    public function open(): mixed;
}
