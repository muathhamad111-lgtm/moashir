<?php

declare(strict_types=1);

namespace App\Contracts\Files;

interface ClamdConnector
{
    /**
     * @return resource
     */
    public function connect();
}
