<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Platform\StorageMeasurement;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Date;

final readonly class MeasureStorage
{
    public function __construct(private Filesystems $filesystems, private Config $config) {}

    public function handle(): StorageMeasurement
    {
        $disk = $this->filesystems->disk($this->config->string('moashir.files.disk'));
        $files = array_values($disk->allFiles());
        $measuredAt = Date::now();

        return StorageMeasurement::query()->updateOrCreate(
            ['measured_on' => $measuredAt->copy()->setTimezone($this->config->string('app.display_timezone'))->toDateString()],
            ['used_bytes' => $this->sizeOf($disk, $files), 'file_count' => count($files), 'measured_at' => $measuredAt],
        );
    }

    /**
     * @param  list<string>  $files
     */
    private function sizeOf(Filesystem $disk, array $files): int
    {
        $bytes = 0;

        foreach ($files as $file) {
            $bytes += $disk->size($file);
        }

        return $bytes;
    }
}
