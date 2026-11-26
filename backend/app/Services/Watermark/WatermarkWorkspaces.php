<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use Illuminate\Filesystem\Filesystem;

final readonly class WatermarkWorkspaces
{
    public function __construct(private Filesystem $files) {}

    public function open(): WatermarkWorkspace
    {
        return new WatermarkWorkspace($this->files)->open();
    }
}
