<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\Certificates\CertificateLevel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

final class DocumentAssets
{
    private const string ROOT = 'documents';

    private const array FONTS = [
        'montserrat-latin-variable.woff2' => 'font/woff2',
        'IBMPlexSansArabic-Regular.ttf' => 'font/ttf',
        'IBMPlexSansArabic-SemiBold.ttf' => 'font/ttf',
        'IBMPlexSansArabic-Bold.ttf' => 'font/ttf',
        'hacen-maghreb-regular.ttf' => 'font/ttf',
        'hacen-maghreb-bold.ttf' => 'font/ttf',
    ];

    private const string SVG = 'image/svg+xml';

    private const string PNG = 'image/png';

    /**
     * @var array<string, string>
     */
    private array $encoded = [];

    public function __construct(private readonly Filesystem $files, private readonly Application $app) {}

    public function font(string $file): string
    {
        $mime = self::FONTS[$file] ?? throw new InvalidArgumentException('Unknown document font.');

        return $this->dataUri('fonts/'.$file, $mime);
    }

    /**
     * The licensed fonts are kept out of the public repository, so a checkout may lack them; the
     * documents then fall back to the open-licence font that follows in the stack.
     */
    public function hasFont(string $file): bool
    {
        return array_key_exists($file, self::FONTS) && $this->files->exists($this->pathOf('fonts/'.$file));
    }

    public function logo(): string
    {
        return $this->dataUri('logos/moashir-logo-on-light.svg', self::SVG);
    }

    public function star(): string
    {
        return $this->dataUri('logos/moashir-star.svg', self::SVG);
    }

    public function sampleEntityLogo(): string
    {
        return $this->dataUri('samples/sample-entity-logo.png', self::PNG);
    }

    public function seal(string $level): string
    {
        return $this->dataUri('seals/seal-'.CertificateLevel::from($level)->value.'.svg', self::SVG);
    }

    private function dataUri(string $relativePath, string $mime): string
    {
        return $this->encoded[$relativePath] ??= sprintf(
            'data:%s;base64,%s',
            $mime,
            base64_encode($this->files->get($this->pathOf($relativePath))),
        );
    }

    private function pathOf(string $relativePath): string
    {
        return $this->app->resourcePath(self::ROOT.'/'.$relativePath);
    }
}
