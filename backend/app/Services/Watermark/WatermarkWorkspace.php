<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

final class WatermarkWorkspace
{
    private const int MODE = 0700;

    private ?string $directory = null;

    public function __construct(private readonly Filesystem $files) {}

    public function open(): self
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'moashir-stamp-'.Str::random(24);

        if (! $this->files->makeDirectory($directory, self::MODE)) {
            throw new RuntimeException('The watermark workspace could not be created.');
        }

        $this->directory = $directory;

        return $this;
    }

    public function path(string $name): string
    {
        return ($this->directory ?? throw new RuntimeException('The watermark workspace is not open.')).DIRECTORY_SEPARATOR.$name;
    }

    public function close(): void
    {
        if ($this->directory !== null) {
            $this->files->deleteDirectory($this->directory);
            $this->directory = null;
        }
    }
}
