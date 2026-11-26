<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Http\UploadedFile;
use LogicException;

final readonly class PublicImageIntake
{
    public const string FIELD = 'file';

    private const string SVG_EXTENSION = 'svg';

    public function __construct(private FileIntake $intake, private SvgSafetyCheck $svg) {}

    public function accept(UploadedFile $upload, StoredFilePurpose $purpose, User $uploader): StoredFile
    {
        if (! in_array($purpose, [StoredFilePurpose::CmsImage, StoredFilePurpose::CmsLogo, StoredFilePurpose::KpiImage], true)) {
            throw new LogicException('Only content images, content logos and KPI images are accepted here.');
        }

        if ($purpose->allowsVectorImage() && $this->isSvg($upload)) {
            $this->svg->assertSafe((string) $upload->get(), self::FIELD);
        }

        return $this->intake->accept($upload, $purpose, $uploader, self::FIELD);
    }

    private function isSvg(UploadedFile $upload): bool
    {
        return mb_strtolower($upload->getClientOriginalExtension()) === self::SVG_EXTENSION;
    }
}
