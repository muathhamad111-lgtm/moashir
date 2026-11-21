<?php

declare(strict_types=1);

use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\StoredFileDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

const LOGO_HIDDEN_NOTE = 'Camera serial MSH-0042, shot at the Al-Amal warehouse';

const PNG_HEADER_AND_IHDR_BYTES = 33;

function logoImageBytes(string $type = 'png', int $width = 400, int $height = 300): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 29, 116, 112));
    imagefilledellipse($image, intdiv($width, 2), intdiv($height, 2), intdiv($width, 2), intdiv($height, 2), (int) imagecolorallocate($image, 255, 255, 255));
    $stream = fopen('php://memory', 'w+b');
    $type === 'png' ? imagepng($image, $stream) : imagejpeg($image, $stream, 90);
    rewind($stream);
    $bytes = (string) stream_get_contents($stream);
    fclose($stream);

    return $bytes;
}

function pngWithTextChunk(string $png, string $text): string
{
    $data = "Comment\0".$text;
    $chunk = pack('N', strlen($data)).'tEXt'.$data.pack('N', crc32('tEXt'.$data));

    return substr($png, 0, PNG_HEADER_AND_IHDR_BYTES).$chunk.substr($png, PNG_HEADER_AND_IHDR_BYTES);
}

function jpegWithComment(string $jpeg, string $text): string
{
    return substr($jpeg, 0, 2)."\xFF\xFE".pack('n', strlen($text) + 2).$text.substr($jpeg, 2);
}

function logoUpload(string $contents, string $name = 'شعار-جمعية-الأمل.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function uploadEntityLogo(User $owner, UploadedFile $file): TestResponse
{
    return switchTo($owner)->post('/api/v1/entities/me/logo', ['file' => $file], ['Accept' => 'application/json']);
}

function storedFileBytes(int $fileId): string
{
    return (string) app(StoredFileDisk::class)->contents(StoredFile::withTrashed()->findOrFail($fileId));
}
