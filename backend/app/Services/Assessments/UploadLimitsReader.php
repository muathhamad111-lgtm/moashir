<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\UploadLimits;
use App\Services\Files\FileSettings;

final readonly class UploadLimitsReader
{
    public function __construct(private FileSettings $settings) {}

    public function read(): UploadLimits
    {
        return new UploadLimits($this->settings->maxUploadMegabytes(), $this->settings->evidenceTypes(), $this->settings->evidenceMaxFilesPerSub());
    }
}
