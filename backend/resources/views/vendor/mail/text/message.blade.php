@props(['frame', 'mail', 'heading', 'preheader' => '', 'reason' => null])
{{ $heading }}

{{ $slot }}

{{ __('mail.sign_off') }}
{{ __('mail.team') }}

--
{{ __('mail.copyright', ['year' => $frame->year]) }}
{{ __('mail.privacy') }}: {{ $frame->privacyUrl }}
{{ __('mail.terms') }}: {{ $frame->termsUrl }}
@if ($reason)
{{ __('mail.reasons.'.$reason->value) }}
@endif
