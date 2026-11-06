<!DOCTYPE html>
<html lang="{{ $page->locale }}" dir="{{ $page->direction }}">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'">
<title>@yield('title')</title>
<style>
@include('documents.partials.fonts')
@include('documents.partials.tokens')
@include('documents.partials.base')
@yield('styles')
</style>
</head>
<body>
@include('documents.partials.watermark')
@yield('content')
</body>
</html>
