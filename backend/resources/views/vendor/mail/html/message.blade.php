@props(['frame', 'mail', 'heading', 'preheader' => '', 'reason' => null])
<!DOCTYPE html>
<html lang="{{ $frame->locale }}" dir="{{ $frame->direction }}">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $heading }}</title>
<style>
@include('vendor.mail.html.themes.adaptive')
</style>
</head>
<body class="mail-body mail-{{ $frame->direction }}" dir="{{ $frame->direction }}">
<div class="mail-preheader">{{ $preheader }}</div>
<table class="mail-wrapper" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="{{ $frame->direction }}">
<tr>
<td class="mail-wrapper-cell" align="center">
<table class="mail-container" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" dir="{{ $frame->direction }}">
<tr>
<td class="mail-header" align="{{ $frame->align }}">
<img class="mail-logo" src="{{ $mail->embed($frame->logoPath) }}" width="180" height="105" alt="{{ __('mail.brand') }}">
</td>
</tr>
<tr>
<td class="mail-accent" height="4"></td>
</tr>
<tr>
<td class="mail-card" align="{{ $frame->align }}">
<h1 class="mail-heading">{{ $heading }}</h1>
{{ $slot }}
<p class="mail-signature">{{ __('mail.sign_off') }}<br>{{ __('mail.team') }}</p>
</td>
</tr>
<tr>
<td class="mail-footer" align="center">
<p class="mail-footer-text">{{ __('mail.copyright', ['year' => $frame->year]) }}</p>
<p class="mail-footer-text"><a class="mail-footer-link" href="{{ $frame->privacyUrl }}">{{ __('mail.privacy') }}</a> &middot; <a class="mail-footer-link" href="{{ $frame->termsUrl }}">{{ __('mail.terms') }}</a></p>
@if ($reason)
<p class="mail-footer-text">{{ __('mail.reasons.'.$reason->value) }}</p>
@endif
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
