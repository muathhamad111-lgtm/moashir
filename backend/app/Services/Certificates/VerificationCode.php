<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use Illuminate\Contracts\Config\Repository;

final readonly class VerificationCode
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int GROUP = 4;

    private const string SEPARATOR = '-';

    public function __construct(private Repository $config) {}

    public function generate(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < $this->length(); $index++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return $code;
    }

    public function normalize(string $input): ?string
    {
        $code = strtoupper((string) preg_replace('/[\s\-]+/', '', $input));

        return strlen($code) === $this->length() && strspn($code, self::ALPHABET) === strlen($code) ? $code : null;
    }

    public static function display(string $code): string
    {
        return implode(self::SEPARATOR, str_split($code, self::GROUP));
    }

    private function length(): int
    {
        return $this->config->integer('moashir.certificates.verification_code_length');
    }
}
