<?php

declare(strict_types=1);

use App\Services\Files\SvgSafetyCheck;
use App\Support\Files\ContentSignature;

it('accepts plain drawing markup, local references, embedded rasters and editor metadata', function (string $markup): void {
    expect(new SvgSafetyCheck()->isSafe($markup))->toBeTrue();
})->with([
    'shapes' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><g><path d="M0 0h10v10z" fill="#0d6b6b"/><circle cx="5" cy="5" r="2"/></g></svg>'],
    'gradient and local url' => ['<svg xmlns="http://www.w3.org/2000/svg"><defs><radialGradient id="r"><stop offset="1"/></radialGradient><clipPath id="c"><rect width="4" height="4"/></clipPath></defs><rect fill="url(#r)" clip-path="url( \'#c\' )" width="9" height="9"/></svg>'],
    'local use' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><symbol id="s"><rect width="1" height="1"/></symbol><use xlink:href="#s"/></svg>'],
    'embedded png' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/png;base64,iVBORw0KGgo=" width="1" height="1"/></svg>'],
    'inline style' => ['<svg xmlns="http://www.w3.org/2000/svg"><style>.a{fill:#123456}</style><rect class="a" style="stroke:#000" width="1" height="1"/></svg>'],
    'inkscape export' => ['<?xml version="1.0"?><!-- Created with Inkscape --><svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#"><sodipodi:namedview inkscape:zoom="1"/><metadata><rdf:RDF><cc:Work><dc:title>Logo</dc:title></cc:Work></rdf:RDF></metadata><g inkscape:label="Layer 1"><text>مؤشر</text></g></svg>'],
]);

it('rejects anything that could run script, load a resource or is not an SVG drawing', function (string $markup): void {
    expect(new SvgSafetyCheck()->isSafe($markup))->toBeFalse();
})->with([
    'script element' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'uppercase handler' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect ONCLICK="alert(1)"/></svg>'],
    'animation' => ['<svg xmlns="http://www.w3.org/2000/svg"><a><animate attributeName="href" values="javascript:alert(1)"/></a></svg>'],
    'link element' => ['<svg xmlns="http://www.w3.org/2000/svg"><a href="https://example.org"><rect/></a></svg>'],
    'external image' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.org/a.png"/></svg>'],
    'svg in data url' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/svg+xml;base64,PHN2Zz4="/></svg>'],
    'spaced script url' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect fill="java script:x" style="x:j a v a s c r i p t:1"/></svg>'],
    'external filter url' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect filter="url(https://example.org/f.svg#f)"/></svg>'],
    'external style url' => ['<svg xmlns="http://www.w3.org/2000/svg"><style>rect{fill:url("https://example.org/p.svg#p")}</style></svg>'],
    'entity declaration' => ['<!DOCTYPE svg [<!ENTITY a "b">]><svg xmlns="http://www.w3.org/2000/svg"/>'],
    'processing instruction' => ['<svg xmlns="http://www.w3.org/2000/svg"><?php echo 1; ?></svg>'],
    'html root' => ['<html xmlns="http://www.w3.org/1999/xhtml"/>'],
    'svg without namespace' => ['<svg><rect/></svg>'],
    'broken xml' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect></svg>'],
    'not markup' => ['GIF89a'],
]);

it('recognises SVG content after an optional byte order mark and whitespace', function (string $head, bool $matches): void {
    expect(ContentSignature::matches('image/svg+xml', $head))->toBe($matches);
})->with([
    'xml declaration' => ['<?xml version="1.0"', true],
    'bom then svg' => ["\xEF\xBB\xBF  <svg", true],
    'binary' => ["\x89PNG\r\n\x1a\n", false],
    'empty' => ['', false],
]);
