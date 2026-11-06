<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Settings\LogoVariant;
use Illuminate\Http\UploadedFile;

final readonly class BrandingLogoUpload
{
    public function __construct(public UploadedFile $file, public LogoVariant $variant, public ?string $reason) {}
}
