<?php

declare(strict_types=1);

const SCAN_BATCH = 1000;
const CONNECT_TIMEOUT_SECONDS = 5.0;

function setting(string $name): string
{
    $value = getenv($name);

    return is_string($value) ? $value : '';
}

function fail(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}

function sourceConnection(): Redis
{
    $redis = new Redis();
    $redis->connect(setting('REDIS_SOURCE_HOST'), (int) setting('REDIS_SOURCE_PORT'), CONNECT_TIMEOUT_SECONDS);

    if (setting('REDIS_SOURCE_PASSWORD') !== '') {
        $redis->auth(setting('REDIS_SOURCE_PASSWORD'));
    }

    $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

    return $redis;
}

function targetConnection(): Redis
{
    $redis = new Redis();
    $redis->connect(setting('REDIS_TARGET_SOCKET'), 0, CONNECT_TIMEOUT_SECONDS);

    return $redis;
}

function copyDatabase(Redis $source, Redis $target, int $from, int $to, string $prefix): int
{
    $source->select($from);
    $target->select($to);
    $copied = 0;
    $iterator = null;

    while (($keys = $source->scan($iterator, $prefix.'*', SCAN_BATCH)) !== false) {
        foreach ($keys as $key) {
            $payload = $source->dump($key);
            $ttl = $source->pttl($key);

            if (! is_string($payload) || $ttl === -2) {
                continue;
            }

            $target->restore($key, max(0, $ttl), $payload, ['REPLACE']);
            $copied++;
        }
    }

    return $copied;
}

$prefix = setting('REDIS_KEY_PREFIX');

if (preg_match('/^moashir_(staging|production)_$/', $prefix) !== 1) {
    fail('REDIS_KEY_PREFIX must be moashir_staging_ or moashir_production_.');
}

$pairs = array_slice($argv, 1);

if ($pairs === []) {
    fail('Usage: redis-copy.php <source db>:<target db> ...');
}

$source = sourceConnection();
$target = targetConnection();

foreach ($pairs as $pair) {
    if (preg_match('/^(\d{1,2}):(\d)$/', $pair, $match) !== 1) {
        fail("Invalid database pair {$pair}.");
    }

    $copied = copyDatabase($source, $target, (int) $match[1], (int) $match[2], $prefix);
    fwrite(STDOUT, sprintf("Shared Redis database %d -> own database %d: %d key(s) copied.\n", $match[1], $match[2], $copied));
}
