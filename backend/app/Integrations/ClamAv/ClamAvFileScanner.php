<?php

declare(strict_types=1);

namespace App\Integrations\ClamAv;

use App\Contracts\Files\ClamdConnector;
use App\Contracts\Files\FileScanner;
use App\Data\Files\ScanVerdict;
use App\Exceptions\Files\FileScannerUnavailable;
use ErrorException;

final readonly class ClamAvFileScanner implements FileScanner
{
    public const string ENGINE = 'clamav';

    private const string COMMAND = "zINSTREAM\0";

    private const int CHUNK_BYTES = 8192;

    private const int MAX_REPLY_BYTES = 1024;

    public function __construct(private ClamdConnector $connector) {}

    public function scan($stream): ScanVerdict
    {
        try {
            $socket = $this->connector->connect();
        } catch (FileScannerUnavailable) {
            return ScanVerdict::failed(self::ENGINE);
        }

        try {
            $reply = $this->send($socket, $stream) ? $this->readReply($socket) : null;
        } finally {
            fclose($socket);
        }

        return $reply === null ? ScanVerdict::failed(self::ENGINE) : $this->verdictOf($reply);
    }

    /**
     * @param  resource  $socket
     * @param  resource  $stream
     */
    private function send($socket, $stream): bool
    {
        if (! $this->write($socket, self::COMMAND)) {
            return false;
        }

        while (! feof($stream)) {
            $chunk = fread($stream, self::CHUNK_BYTES);

            if ($chunk === false) {
                return false;
            }

            if ($chunk !== '' && ! $this->write($socket, pack('N', strlen($chunk)).$chunk)) {
                return false;
            }
        }

        return $this->write($socket, pack('N', 0));
    }

    /**
     * @param  resource  $socket
     */
    private function write($socket, string $bytes): bool
    {
        $written = 0;

        try {
            while ($written < strlen($bytes)) {
                $sent = fwrite($socket, substr($bytes, $written));

                if ($sent === false || $sent === 0) {
                    return false;
                }

                $written += $sent;
            }
        } catch (ErrorException) {
            return false;
        }

        return true;
    }

    /**
     * @param  resource  $socket
     */
    private function readReply($socket): ?string
    {
        $reply = '';

        while (! str_contains($reply, "\0") && strlen($reply) < self::MAX_REPLY_BYTES && ! feof($socket)) {
            try {
                $part = fread($socket, self::MAX_REPLY_BYTES);
            } catch (ErrorException) {
                return null;
            }

            if ($part === false || ($part === '' && stream_get_meta_data($socket)['timed_out'])) {
                return null;
            }

            $reply .= $part;
        }

        return $reply === '' ? null : trim(strstr($reply, "\0", true) ?: $reply);
    }

    private function verdictOf(string $reply): ScanVerdict
    {
        if (preg_match('/^stream: OK$/', $reply) === 1) {
            return ScanVerdict::clean(self::ENGINE);
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $matches) === 1) {
            return ScanVerdict::infected(self::ENGINE, $matches[1]);
        }

        return ScanVerdict::failed(self::ENGINE);
    }
}
