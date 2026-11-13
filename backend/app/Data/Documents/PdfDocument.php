<?php

declare(strict_types=1);

namespace App\Data\Documents;

final readonly class PdfDocument
{
    public const string DEFAULT_LOCALE = 'ar';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $template,
        public array $data,
        public ?string $watermark = null,
        public string $locale = self::DEFAULT_LOCALE,
    ) {}

    public function direction(): string
    {
        return $this->locale === 'en' ? 'ltr' : 'rtl';
    }
}
