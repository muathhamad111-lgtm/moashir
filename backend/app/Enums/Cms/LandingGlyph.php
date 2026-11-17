<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum LandingGlyph: string
{
    case People = 'people';
    case Institution = 'institution';
    case Endowment = 'endowment';
    case Gauge = 'gauge';
    case GapBars = 'gap_bars';
    case Medal = 'medal';
    case Lens = 'lens';
    case Trophy = 'trophy';
    case Growth = 'growth';
    case Grid = 'grid';
    case Clipboard = 'clipboard';
    case Shield = 'shield';
    case Stairs = 'stairs';
    case Cycle = 'cycle';
    case Target = 'target';
    case Document = 'document';
    case Map = 'map';
}
