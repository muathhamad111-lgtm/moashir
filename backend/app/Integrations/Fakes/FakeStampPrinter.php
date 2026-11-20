<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Documents\StampPrinter;
use App\Data\Watermark\StampSheet;
use App\Support\ValueObjects\PageSize;

final class FakeStampPrinter implements StampPrinter
{
    /**
     * @var list<StampSheet>
     */
    private array $printed = [];

    public function print(StampSheet $sheet): string
    {
        $this->printed[] = $sheet;
        $text = $this->asciiOf($sheet->caption);
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];

        foreach ($sheet->sizes as $size) {
            $pageNumber = count($objects) + 1;
            $kids[] = $pageNumber.' 0 R';
            $objects[] = $this->pageObject($size, $pageNumber + 1);
            $objects[] = $this->contentObject($text);
        }

        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';

        return $this->document($objects);
    }

    /**
     * @return list<StampSheet>
     */
    public function printed(): array
    {
        return $this->printed;
    }

    private function pageObject(PageSize $size, int $contentNumber): string
    {
        return sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>', $size->width, $size->height, $contentNumber);
    }

    private function contentObject(string $text): string
    {
        $stream = 'BT /F1 8 Tf 12 12 Td ('.$text.') Tj ET';

        return '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
    }

    /**
     * @param  list<string>  $objects
     */
    private function document(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function asciiOf(string $caption): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '', $caption);

        return strtr($ascii, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}
