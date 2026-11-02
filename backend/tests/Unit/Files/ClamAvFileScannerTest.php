<?php

declare(strict_types=1);

use App\Contracts\Files\ClamdConnector;
use App\Enums\Files\MalwareScanStatus;
use App\Exceptions\Files\FileScannerUnavailable;
use App\Integrations\ClamAv\ClamAvFileScanner;
use App\Integrations\ClamAv\SocketClamdConnector;
use App\Support\Files\ContentSignature;
use Illuminate\Support\Facades\Config;

/**
 * @return array{0: ClamdConnector, 1: resource}
 */
function pairedClamd(string $reply): array
{
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

    if ($pair === false) {
        throw new RuntimeException('A socket pair is required for this test.');
    }

    [$client, $daemon] = $pair;
    fwrite($daemon, $reply);

    return [new readonly class($client) implements ClamdConnector
    {
        /**
         * @param  resource  $socket
         */
        public function __construct(private mixed $socket) {}

        public function connect()
        {
            return $this->socket;
        }
    }, $daemon];
}

/**
 * @return resource
 */
function memoryStream(string $contents)
{
    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, $contents);
    rewind($stream);

    return $stream;
}

it('streams the file to clamd with the INSTREAM protocol and reads a clean verdict', function (): void {
    [$connector, $daemon] = pairedClamd("stream: OK\0");

    $verdict = new ClamAvFileScanner($connector)->scan(memoryStream('%PDF-1.4 bank transfer'));
    $sent = stream_get_contents($daemon);

    expect($verdict->status)->toBe(MalwareScanStatus::Clean)
        ->and($verdict->engine)->toBe('clamav')
        ->and($sent)->toBe("zINSTREAM\0".pack('N', 22).'%PDF-1.4 bank transfer'.pack('N', 0));
});

it('reports the signature of an infected file', function (): void {
    [$connector, $daemon] = pairedClamd("stream: Eicar-Test-Signature FOUND\0");

    $verdict = new ClamAvFileScanner($connector)->scan(memoryStream('payload'));

    expect($verdict->status)->toBe(MalwareScanStatus::Infected)
        ->and($verdict->signature)->toBe('Eicar-Test-Signature')
        ->and(is_resource($daemon))->toBeTrue();
});

it('fails closed on an error reply, an empty reply or an unreachable daemon', function (): void {
    [$errorConnector, $errorDaemon] = pairedClamd("INSTREAM size limit exceeded. ERROR\0");
    [$silentConnector, $daemon] = pairedClamd('');
    fclose($daemon);
    $unreachable = new readonly class implements ClamdConnector
    {
        public function connect(): never
        {
            throw FileScannerUnavailable::unreachable();
        }
    };

    expect(new ClamAvFileScanner($errorConnector)->scan(memoryStream('payload'))->status)->toBe(MalwareScanStatus::Failed)
        ->and(new ClamAvFileScanner($silentConnector)->scan(memoryStream('payload'))->status)->toBe(MalwareScanStatus::Failed)
        ->and(new ClamAvFileScanner($unreachable)->scan(memoryStream('payload'))->status)->toBe(MalwareScanStatus::Failed)
        ->and(is_resource($errorDaemon))->toBeTrue();
});

it('reports an unreachable clamd socket as unavailable', function (): void {
    Config::set('moashir.files.clamav.socket', 'unix:///nonexistent/moashir-clamd.sock');
    Config::set('moashir.files.clamav.timeout_seconds', 1);

    expect(fn () => app(SocketClamdConnector::class)->connect())->toThrow(FileScannerUnavailable::class);
});

it('checks the magic bytes of uploaded content', function (string $mime, string $head, bool $matches): void {
    expect(ContentSignature::matches($mime, $head))->toBe($matches);
})->with([
    ['application/pdf', '%PDF-1.7', true],
    ['application/pdf', "\x89PNG\r\n\x1a\n", false],
    ['image/png', "\x89PNG\r\n\x1a\nIHDR", true],
    ['image/jpeg', "\xFF\xD8\xFF\xE0", true],
    ['image/jpeg', 'GIF89a', false],
    ['text/plain', '7B2270737049', true],
    ['text/plain', "7B22\0", false],
    ['application/zip', 'PK', false],
]);
