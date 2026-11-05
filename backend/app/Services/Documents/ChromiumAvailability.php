<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Illuminate\Contracts\Config\Repository;
use Psr\Log\LoggerInterface;

final readonly class ChromiumAvailability
{
    public const string CHROMIUM = 'chromium';

    public function __construct(private Repository $config, private LoggerInterface $logger) {}

    public function isReady(): bool
    {
        if ($this->config->get('moashir.documents.pdf_renderer') !== self::CHROMIUM) {
            return false;
        }

        $binary = $this->config->get('moashir.documents.chrome_binary');

        if (is_string($binary) && $binary !== '' && is_file($binary) && is_executable($binary)) {
            return true;
        }

        $this->logger->critical('documents.chrome_binary_unusable', ['setting' => 'moashir.documents.chrome_binary']);

        return false;
    }
}
