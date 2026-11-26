<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Exceptions\Files\FileRejected;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMProcessingInstruction;
use DOMXPath;

/**
 * Accepts an SVG logo only when every element is a drawing element from a strict allowlist and nothing in it can run
 * script or reach outside the file. Anything else is rejected rather than cleaned, so a stored logo is always the
 * exact file its uploader exported.
 */
final readonly class SvgSafetyCheck
{
    private const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const array EDITOR_NAMESPACES = [
        'http://www.inkscape.org/namespaces/inkscape',
        'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd',
        'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'http://creativecommons.org/ns#',
        'http://purl.org/dc/elements/1.1/',
    ];

    private const array ELEMENTS = [
        'svg', 'g', 'defs', 'title', 'desc', 'metadata', 'symbol', 'use', 'path', 'rect', 'circle', 'ellipse', 'line',
        'polyline', 'polygon', 'text', 'tspan', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask',
        'pattern', 'style', 'image',
    ];

    private const string DECLARATIONS = '/<!(?:DOCTYPE|ENTITY)/i';

    private const string EMBEDDED_RASTER = '#^data:image/(?:png|jpeg);base64,[A-Za-z0-9+/=\s]+$#';

    private const string URL_REFERENCE = '/url\(\s*[\'"]?\s*([^\'")\s]*)/i';

    private const string SCRIPT_URL = '/j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t\s*:/i';

    public function assertSafe(string $contents, string $field): void
    {
        if (! $this->isSafe($contents)) {
            throw FileRejected::unsafeSvg($field);
        }
    }

    public function isSafe(string $contents): bool
    {
        if (preg_match(self::DECLARATIONS, $contents) === 1) {
            return false;
        }

        $document = $this->parse($contents);

        return $document instanceof DOMDocument && $this->hasSvgRoot($document) && $this->allNodesSafe($document);
    }

    private function parse(string $contents): ?DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadXML($contents, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    private function hasSvgRoot(DOMDocument $document): bool
    {
        $root = $document->documentElement;

        return $root instanceof DOMElement && $root->namespaceURI === self::SVG_NAMESPACE && $root->localName === 'svg';
    }

    private function allNodesSafe(DOMDocument $document): bool
    {
        $nodes = new DOMXPath($document)->query('//node()');

        if ($nodes === false) {
            return false;
        }

        foreach ($nodes as $node) {
            if (! $this->isSafeNode($node)) {
                return false;
            }
        }

        return true;
    }

    private function isSafeNode(DOMNode|DOMNameSpaceNode $node): bool
    {
        if ($node instanceof DOMProcessingInstruction) {
            return false;
        }

        return ! $node instanceof DOMElement || $this->isSafeElement($node);
    }

    private function isSafeElement(DOMElement $element): bool
    {
        if (! $this->isAllowedElement($element)) {
            return false;
        }

        if (mb_strtolower((string) $element->localName) === 'style' && ! $this->isSafeStyle($element->textContent)) {
            return false;
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if (! $this->isSafeAttribute($element, $attribute)) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedElement(DOMElement $element): bool
    {
        if (in_array($element->namespaceURI, self::EDITOR_NAMESPACES, true)) {
            return true;
        }

        return $element->namespaceURI === self::SVG_NAMESPACE
            && in_array(mb_strtolower((string) $element->localName), self::ELEMENTS, true);
    }

    private function isSafeAttribute(DOMElement $element, DOMAttr $attribute): bool
    {
        $name = mb_strtolower((string) $attribute->localName);
        $value = $attribute->value;

        if (str_starts_with($name, 'on') || preg_match(self::SCRIPT_URL, $value) === 1) {
            return false;
        }

        if ($name === 'href') {
            return $this->isSafeReference($element, $value);
        }

        return $this->hasOnlyLocalUrls($value);
    }

    private function isSafeReference(DOMElement $element, string $value): bool
    {
        $reference = trim($value);

        if (str_starts_with($reference, '#')) {
            return true;
        }

        return mb_strtolower((string) $element->localName) === 'image' && preg_match(self::EMBEDDED_RASTER, $reference) === 1;
    }

    private function isSafeStyle(string $css): bool
    {
        return stripos($css, '@import') === false
            && preg_match(self::SCRIPT_URL, $css) !== 1
            && $this->hasOnlyLocalUrls($css);
    }

    private function hasOnlyLocalUrls(string $value): bool
    {
        preg_match_all(self::URL_REFERENCE, $value, $matches);

        return array_all($matches[1], fn (string $target): bool => str_starts_with($target, '#'));
    }
}
