<?php

declare(strict_types=1);

namespace App\Integrations\Watermark;

use App\Contracts\Watermark\OfficeConverter;
use App\Exceptions\Watermark\WatermarkFailed;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory as ProcessFactory;

final readonly class LibreOfficeConverter implements OfficeConverter
{
    public function __construct(private ProcessFactory $processes, private Repository $config) {}

    public function toPdf(string $source, string $outputDirectory, string $profileDirectory): string
    {
        try {
            $result = $this->processes->newPendingProcess()
                ->timeout($this->config->integer('moashir.watermark.conversion_timeout_seconds'))
                ->env(['HOME' => $profileDirectory])
                ->run([
                    $this->config->string('moashir.watermark.soffice_binary'),
                    '-env:UserInstallation=file://'.$profileDirectory,
                    '--headless', '--norestore', '--nologo', '--nolockcheck', '--nodefault',
                    '--convert-to', 'pdf',
                    '--outdir', $outputDirectory,
                    $source,
                ]);
        } catch (ProcessTimedOutException) {
            throw WatermarkFailed::conversionFailed();
        }
        $pdf = $outputDirectory.DIRECTORY_SEPARATOR.pathinfo($source, PATHINFO_FILENAME).'.pdf';

        if (! $result->successful() || ! is_file($pdf)) {
            throw WatermarkFailed::conversionFailed();
        }

        return $pdf;
    }
}
