<?php

declare(strict_types=1);

const ITERATIONS = 4096;
const SALT_BYTES = 16;

$password = stream_get_contents(STDIN);

if (! is_string($password) || preg_match('/^[A-Za-z0-9]{24,}$/', $password) !== 1) {
    fwrite(STDERR, "Expected a random alphanumeric password of at least 24 characters on standard input.\n");
    exit(1);
}

$salt = random_bytes(SALT_BYTES);
$saltedPassword = hash_pbkdf2('sha256', $password, $salt, ITERATIONS, 32, true);
$storedKey = hash('sha256', hash_hmac('sha256', 'Client Key', $saltedPassword, true), true);
$serverKey = hash_hmac('sha256', 'Server Key', $saltedPassword, true);

fwrite(STDOUT, sprintf(
    'SCRAM-SHA-256$%d:%s$%s:%s',
    ITERATIONS,
    base64_encode($salt),
    base64_encode($storedKey),
    base64_encode($serverKey),
));
