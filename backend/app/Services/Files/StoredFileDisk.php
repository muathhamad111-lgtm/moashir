<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class StoredFileDisk
{
    private const string QUARANTINE_DIRECTORY = 'quarantine';

    public function __construct(private Filesystems $filesystems, private Repository $config) {}

    public function name(): string
    {
        return $this->config->string('moashir.files.disk');
    }

    public function newPath(StoredFilePurpose $purpose, ?string $extension): string
    {
        $name = Str::uuid()->toString().($extension === null || $extension === '' ? '' : '.'.$extension);

        return $purpose->directory().'/'.Date::now()->format('Y/m').'/'.$name;
    }

    public function put(string $path, string $contents): void
    {
        if (! $this->disk()->put($path, $contents)) {
            throw new RuntimeException('The file could not be stored.');
        }
    }

    /**
     * @param  resource  $stream
     */
    public function putStream(string $path, $stream): void
    {
        if (! $this->disk()->writeStream($path, $stream)) {
            throw new RuntimeException('The file could not be stored.');
        }
    }

    /**
     * @return resource
     */
    public function readStream(StoredFile $file)
    {
        $stream = $this->filesystems->disk($file->disk)->readStream($file->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The stored file could not be read.');
        }

        return $stream;
    }

    public function contents(StoredFile $file): ?string
    {
        return $this->filesystems->disk($file->disk)->get($file->path);
    }

    public function delete(StoredFile $file): void
    {
        $this->filesystems->disk($file->disk)->delete($file->path);
    }

    public function quarantinePath(StoredFile $file): string
    {
        return self::QUARANTINE_DIRECTORY.'/'.$file->public_id;
    }

    public function moveIntoQuarantine(StoredFile $file): string
    {
        $target = $this->quarantinePath($file);
        $disk = $this->filesystems->disk($file->disk);

        if ($file->path !== $target && $disk->exists($file->path) && ! $disk->move($file->path, $target)) {
            throw new RuntimeException('The file could not be moved into quarantine.');
        }

        return $target;
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk($this->name());
    }
}
