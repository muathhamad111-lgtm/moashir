<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Files\RunWatermarkSmokeCheck;
use App\Exceptions\ApiException;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:files:watermark-smoke')]
final class WatermarkSmokeCommand extends Command
{
    protected $signature = 'moashir:files:watermark-smoke';

    protected $description = 'Convert a small Word file with LibreOffice and stamp the PDF with a sample viewer stamp, without touching the database.';

    public function handle(RunWatermarkSmokeCheck $check): int
    {
        try {
            $result = $check->handle();
        } catch (ApiException $failure) {
            $this->components->error('Watermark smoke check failed: '.$failure->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Converted and stamped %d page(s) in %.1f s.', $result->pages, $result->seconds));

        return self::SUCCESS;
    }
}
