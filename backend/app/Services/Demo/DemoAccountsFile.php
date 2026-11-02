<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoAccount;
use App\Exceptions\Console\DemoDataRefused;
use ErrorException;
use Illuminate\Support\Facades\Date;

final class DemoAccountsFile
{
    private const int FILE_MODE = 0600;

    /**
     * @var resource|null
     */
    private $handle;

    private function __construct(private readonly string $path)
    {
        $previousMask = umask(0077);

        try {
            $handle = fopen($path, 'x');
        } catch (ErrorException) {
            $handle = false;
        } finally {
            umask($previousMask);
        }

        if ($handle === false) {
            throw DemoDataRefused::accountsFileRejected('cannot be created');
        }

        chmod($path, self::FILE_MODE);
        $this->handle = $handle;
    }

    public static function create(string $path, string $applicationBasePath): self
    {
        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw DemoDataRefused::accountsFileRejected('path must be absolute');
        }

        if (file_exists($path) || is_link($path)) {
            throw DemoDataRefused::accountsFileRejected('already exists and is never overwritten');
        }

        $directory = realpath(dirname($path));
        $base = realpath($applicationBasePath);

        if ($directory === false || ! is_dir($directory) || ! is_writable($directory)) {
            throw DemoDataRefused::accountsFileRejected('directory does not exist or is not writable');
        }

        if ($base !== false && ($directory === $base || str_starts_with($directory.DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR))) {
            throw DemoDataRefused::accountsFileRejected('must be outside the application directory');
        }

        return new self($directory.DIRECTORY_SEPARATOR.basename($path));
    }

    /**
     * @param  list<DemoAccount>  $accounts
     */
    public function write(string $environment, array $accounts): void
    {
        $handle = $this->openHandle();
        fwrite($handle, $this->render($environment, $accounts));
        fflush($handle);
        fclose($handle);
        $this->handle = null;
    }

    public function discard(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;

        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return resource
     */
    private function openHandle()
    {
        if (! is_resource($this->handle)) {
            throw DemoDataRefused::accountsFileRejected('was already written');
        }

        return $this->handle;
    }

    /**
     * @param  list<DemoAccount>  $accounts
     */
    private function render(string $environment, array $accounts): string
    {
        $lines = [
            sprintf('Moashir demo accounts - environment: %s - generated: %s', $environment, Date::now()->toIso8601String()),
            'Fictional demo data only. Staff accounts must enrol an authenticator app (TOTP) at the first sign-in.',
            '',
        ];

        foreach ($accounts as $account) {
            $lines[] = 'role:     '.$account->role;
            $lines[] = 'name:     '.$account->name;
            $lines[] = 'email:    '.$account->email;
            $lines[] = 'password: '.$account->password->reveal();
            $lines[] = 'mfa:      '.($account->mfaRequired ? 'required (TOTP enrolment at first sign-in)' : 'optional');
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }
}
