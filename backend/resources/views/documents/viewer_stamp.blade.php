<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; font-src data:; style-src 'unsafe-inline'">
<title>{{ __('documents.viewer_stamp.title') }}</title>
<style>
@include('documents.partials.fonts')
@foreach ($sizes as $index => $size)
@page sheet-{{ $index }} { size: {{ $size->width }}pt {{ $size->height }}pt; margin: 0; }
.sheet-{{ $index }} { page: sheet-{{ $index }}; width: {{ $size->width }}pt; height: {{ $size->height }}pt; }
@endforeach
html, body { margin: 0; padding: 0; background: transparent; }
.sheet { position: relative; overflow: hidden; break-after: page; }
.sheet:last-child { break-after: auto; }
.stamp { position: absolute; inset: 0; display: block; }
.stamp text { fill: rgb(10, 35, 37); fill-opacity: 0.22; font-family: 'Moashir Latin', 'Moashir Body', sans-serif; font-weight: 600; }
</style>
</head>
<body>
@foreach ($sizes as $index => $size)
@php($placement = $placements[$index])
<div class="sheet sheet-{{ $index }}" aria-hidden="true">
<svg class="stamp" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {{ $size->width }} {{ $size->height }}" width="{{ $size->width }}pt" height="{{ $size->height }}pt"><text x="{{ $placement->centerX }}" y="{{ $placement->centerY }}" transform="rotate(-{{ $placement->angle }} {{ $placement->centerX }} {{ $placement->centerY }})" text-anchor="middle" dominant-baseline="central" font-size="{{ $placement->fontSize }}" textLength="{{ $placement->length }}" lengthAdjust="spacingAndGlyphs" direction="ltr">{{ $line }}</text></svg>
</div>
@endforeach
</body>
</html>
