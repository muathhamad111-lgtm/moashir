@props(['frame', 'mail', 'heading', 'preheader' => '', 'reason' => null, 'summary' => null, 'category' => null])
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
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td><![endif]-->
<table class="mail-container" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" dir="{{ $frame->direction }}">
<tr>
<td class="mail-band" bgcolor="#0a2325">
<table class="mail-band-table" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="mail-band-content" align="{{ $frame->align }}">
<table class="mail-band-top" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="mail-band-logo-cell" align="{{ $frame->align }}" valign="middle">
<img class="mail-logo" src="{{ $mail->embed($frame->logoPath) }}" width="88" height="51" alt="{{ __('mail.brand') }}">
</td>
@if ($category)
<td class="mail-band-chip-cell" align="{{ $frame->endAlign }}" valign="middle">
<table class="mail-chip-table" role="presentation" cellpadding="0" cellspacing="0" border="0" align="{{ $frame->endAlign }}">
<tr><td class="mail-chip">{{ __('mail.categories.'.$category->value) }}</td></tr>
</table>
</td>
@endif
</tr>
</table>
<h1 class="mail-heading">{{ $heading }}</h1>
@if ($summary)
<p class="mail-summary">{{ $summary }}</p>
@endif
</td>
</tr>
<tr>
<td class="mail-card-cap" bgcolor="#ffffff">&nbsp;</td>
</tr>
</table>
</td>
</tr>
<tr>
<td class="mail-card-outer">
<table class="mail-card-table" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="mail-card" align="{{ $frame->align }}" bgcolor="#ffffff">
{{ $slot }}
<p class="mail-signature">{{ __('mail.sign_off') }}<br>{{ __('mail.team') }}</p>
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td class="mail-footer" align="center">
<p class="mail-footer-text mail-footer-links"><a class="mail-footer-link" href="{{ $frame->contactUrl }}">{{ __('mail.contact') }}</a> &middot; <a class="mail-footer-link" href="{{ $frame->privacyUrl }}">{{ __('mail.privacy') }}</a> &middot; <a class="mail-footer-link" href="{{ $frame->termsUrl }}">{{ __('mail.terms') }}</a></p>
@if ($reason)
<p class="mail-footer-text">{{ __('mail.reasons.'.$reason->value) }}</p>
@endif
<p class="mail-footer-text">{{ __('mail.no_reply') }}</p>
<p class="mail-footer-text mail-footer-copyright">{{ __('mail.copyright', ['year' => $frame->year]) }}</p>
</td>
</tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>
</table>
</body>
</html>
