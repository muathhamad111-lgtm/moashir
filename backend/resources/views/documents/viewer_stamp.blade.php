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
.tiles { position: absolute; inset: -50%; display: flex; flex-wrap: wrap; align-content: space-around; justify-content: space-around; transform: rotate(-30deg); color: rgba(10, 35, 37, 0.13); font-family: 'Moashir Body', 'Moashir Latin', sans-serif; font-size: 13pt; font-weight: 600; }
.tiles span { padding: 26pt 20pt; white-space: nowrap; unicode-bidi: plaintext; }
.footer { position: absolute; inset-inline: 12pt; bottom: 7pt; text-align: center; color: rgba(10, 35, 37, 0.6); font-family: 'Moashir Body', 'Moashir Latin', sans-serif; font-size: 7pt; unicode-bidi: plaintext; }
</style>
</head>
<body>
@foreach ($sizes as $index => $size)
<div class="sheet sheet-{{ $index }}" aria-hidden="true">
<div class="tiles">@for ($tile = 0; $tile < $tiles[$index]; $tile++)<span>{{ $identity }}</span>@endfor</div>
<div class="footer">{{ $footer }}</div>
</div>
@endforeach
</body>
</html>
