<?php

declare(strict_types=1);

namespace App\Integrations\ClamAv;

use App\Contracts\Files\ClamdConnector;
use App\Exceptions\Files\FileScannerUnavailable;
use ErrorException;
use Illuminate\Contracts\Config\Repository;

final readonly class SocketClamdConnector implements ClamdConnector
{
    public function __construct(private Repository $config) {}

    public function connect()
    {
        $address = $this->config->string('moashir.files.clamav.socket');
        $timeout = $this->config->integer('moashir.files.clamav.timeout_seconds');
        $errorCode = 0;
        $errorMessage = '';

        try {
            $socket = stream_socket_client($address, $errorCode, $errorMessage, $timeout);
        } catch (ErrorException) {
            throw FileScannerUnavailable::unreachable();
        }

        if ($socket === false) {
            throw FileScannerUnavailable::unreachable();
        }

        stream_set_timeout($socket, $timeout);

        return $socket;
    }
}
