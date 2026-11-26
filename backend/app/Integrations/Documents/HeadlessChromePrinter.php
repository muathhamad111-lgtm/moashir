<?php

declare(strict_types=1);

namespace App\Integrations\Documents;

use App\Contracts\Documents\HtmlPdfPrinter;
use App\Exceptions\Documents\DocumentRenderingFailed;
use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Page;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class HeadlessChromePrinter implements HtmlPdfPrinter
{
    private const array BLOCKED_URLS = ['http://*', 'https://*', 'file://*', 'ftp://*', 'ws://*', 'wss://*'];

    private const string FONTS_READY = 'document.fonts.ready.then(() => true)';

    private const string PDF_SIGNATURE = '%PDF-';

    private const int PROFILE_MODE = 0700;

    public function __construct(
        private Repository $config,
        private Filesystem $files,
        private ChromeLaunchOptions $options,
        private ExceptionHandler $exceptions,
    ) {}

    public function print(string $html): string
    {
        $profile = $this->createProfileDirectory();

        try {
            return $this->printWithBrowser($profile, $html);
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);

            throw DocumentRenderingFailed::engineError();
        } finally {
            $this->files->deleteDirectory($profile);
        }
    }

    private function printWithBrowser(string $profile, string $html): string
    {
        $browser = $this->launch($profile);

        try {
            return $this->printPage($browser->createPage(), $html);
        } finally {
            $browser->close();
        }
    }

    private function launch(string $profile): ProcessAwareBrowser
    {
        $factory = new BrowserFactory($this->config->string('moashir.documents.chrome_binary'));

        return $factory->createBrowser($this->options->for(
            $profile,
            $this->timeoutSeconds(),
            $this->config->integer('moashir.documents.browser_startup_timeout_seconds'),
        ));
    }

    private function printPage(Page $page, string $html): string
    {
        $session = $page->getSession();
        $timeout = $this->timeoutSeconds() * 1000;

        $session->sendMessageSync(new Message('Emulation.setScriptExecutionDisabled', ['value' => true]), $timeout);
        $session->sendMessageSync(new Message('Network.enable'), $timeout);
        $session->sendMessageSync(new Message('Network.setBlockedURLs', ['urls' => self::BLOCKED_URLS]), $timeout);
        $page->setHtml($html, $timeout);
        $page->evaluate(self::FONTS_READY)->getReturnValue($timeout);

        $response = $session->sendMessageSync(new Message('Page.printToPDF', [
            'printBackground' => true,
            'preferCSSPageSize' => true,
            'generateTaggedPDF' => true,
            'generateDocumentOutline' => true,
        ]), $timeout);

        return $this->pdfBytesOf($response->isSuccessful() ? $response->getResultData('data') : null);
    }

    private function pdfBytesOf(mixed $encoded): string
    {
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;

        if (! is_string($bytes) || ! str_starts_with($bytes, self::PDF_SIGNATURE)) {
            throw new RuntimeException('The browser did not return a PDF document.');
        }

        return $bytes;
    }

    private function createProfileDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'moashir-pdf-'.Str::random(24);
        $this->files->makeDirectory($directory, self::PROFILE_MODE);

        return $directory;
    }

    private function timeoutSeconds(): int
    {
        return $this->config->integer('moashir.documents.render_timeout_seconds');
    }
}
