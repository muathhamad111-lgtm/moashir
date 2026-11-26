<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Files\GeneratedFile;
use App\Enums\Files\StoredFilePurpose;
use App\Exceptions\Files\FileRejected;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Models\Settings\BrandingTheme;
use App\Services\Files\FileIntake;
use App\Services\Files\ImageReencoder;
use App\Services\Files\StoredFileRemover;
use App\Services\Files\StoredFileWriter;
use App\Support\Files\RasterImage;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;

final readonly class BrandingLogoIntake
{
    public const string FIELD = 'file';

    private const string PNG = 'image/png';

    private const int MIN_SIDE_PX = 1;

    public function __construct(
        private FileIntake $files,
        private ImageReencoder $reencoder,
        private StoredFileWriter $writer,
        private StoredFileRemover $remover,
        private Repository $config,
    ) {}

    public function accept(UploadedFile $upload, BrandingTheme $theme, User $uploader): StoredFile
    {
        $contents = (string) $upload->get();
        $image = $this->acceptablePng($contents, (string) $upload->getMimeType());
        $original = $this->files->accept($upload, StoredFilePurpose::BrandingLogo, $uploader, self::FIELD);
        $reencoded = $this->reencoder->reencode($contents, $image);
        $this->remover->remove($original);

        if ($reencoded === null) {
            throw FileRejected::contentMismatch(self::FIELD);
        }

        return $this->writer->storeGenerated(new GeneratedFile(
            StoredFilePurpose::BrandingLogo,
            $reencoded,
            'branding-logo.'.$image->extension(),
            $image->extension(),
            $image->mimeType,
        ), $theme);
    }

    private function acceptablePng(string $contents, string $mimeType): RasterImage
    {
        $image = RasterImage::of($contents);
        $maximum = $this->config->integer('moashir.branding.logo_max_side_px');

        if (! $image instanceof RasterImage || ! $image->isPng() || $mimeType !== self::PNG || ! $image->hasSidesBetween(self::MIN_SIDE_PX, $maximum)) {
            throw FileRejected::contentMismatch(self::FIELD);
        }

        return $image;
    }
}
