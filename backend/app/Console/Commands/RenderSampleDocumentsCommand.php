<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Documents\PdfRenderer;
use App\Exceptions\ApiException;
use App\Services\Documents\SampleDocuments;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:documents:render-samples')]
final class RenderSampleDocumentsCommand extends Command
{
    protected $signature = 'moashir:documents:render-samples {--output= : Existing, writable directory for the sample PDF files}';

    protected $description = 'Render the sample certificate, feedback reports and receipt (fictional data) with the configured PDF renderer.';

    public function handle(PdfRenderer $renderer, SampleDocuments $samples, Filesystem $files): int
    {
        $output = rtrim((string) $this->option('output'), DIRECTORY_SEPARATOR);

        if ($output === '' || ! $files->isDirectory($output) || ! $files->isWritable($output)) {
            $this->components->error('Give an existing, writable --output directory.');

            return self::FAILURE;
        }

        try {
            foreach ($samples->all() as $name => $document) {
                $path = $output.DIRECTORY_SEPARATOR.$name;
                $files->put($path, $renderer->render($document));
                $this->components->twoColumnDetail($name, number_format((int) $files->size($path)).' bytes');
            }
        } catch (ApiException $exception) {
            $this->components->error('The PDF renderer is not available: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Sample documents rendered with '.$renderer::class.'.');

        return self::SUCCESS;
    }
}
