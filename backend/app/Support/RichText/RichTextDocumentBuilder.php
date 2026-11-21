<?php

declare(strict_types=1);

namespace App\Support\RichText;

use App\Enums\Cms\RichTextLineKind;

final class RichTextDocumentBuilder
{
    private const string HEADING = '/^(#{1,3}) +(.+?)(?: +#+)?$/u';

    /**
     * @var list<RichTextBlock>
     */
    private array $blocks = [];

    private ?RichTextLineKind $pendingKind = null;

    private ?int $pendingStart = null;

    /**
     * @var list<string>
     */
    private array $pendingLines = [];

    public function add(string $rawLine): void
    {
        $line = trim($rawLine);

        if ($line === '') {
            $this->flush();

            return;
        }

        if (preg_match(self::HEADING, $line, $heading) === 1) {
            $this->flush();
            $this->blocks[] = new HeadingBlock(strlen($heading[1]), trim($heading[2]));

            return;
        }

        $this->append(RichTextLine::of($line));
    }

    public function build(): RichTextDocument
    {
        $this->flush();

        return new RichTextDocument($this->blocks);
    }

    private function append(RichTextLine $line): void
    {
        if ($line->kind !== $this->pendingKind) {
            $this->flush();
            $this->pendingKind = $line->kind;
            $this->pendingStart = $line->number;
        }

        $this->pendingLines[] = $line->content;
    }

    private function flush(): void
    {
        if ($this->pendingLines !== []) {
            $this->blocks[] = $this->pendingBlock();
        }

        $this->pendingKind = null;
        $this->pendingStart = null;
        $this->pendingLines = [];
    }

    private function pendingBlock(): RichTextBlock
    {
        return match ($this->pendingKind) {
            RichTextLineKind::BulletItem => new ListBlock(false, null, $this->pendingLines),
            RichTextLineKind::NumberedItem => new ListBlock(true, $this->pendingStart, $this->pendingLines),
            RichTextLineKind::TableRow => TableBlock::fromLines($this->pendingLines),
            default => new ParagraphBlock(implode(' ', $this->pendingLines)),
        };
    }
}
