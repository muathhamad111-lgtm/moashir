<?php

declare(strict_types=1);

use Illuminate\Encryption\Encrypter;

require __DIR__.'/../vendor/autoload.php';

$configuredKey = $_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? getenv('APP_KEY');

if (! is_string($configuredKey) || $configuredKey === '') {
    $runKey = 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));

    $_SERVER['APP_KEY'] = $runKey;
    $_ENV['APP_KEY'] = $runKey;
    putenv('APP_KEY='.$runKey);
}
