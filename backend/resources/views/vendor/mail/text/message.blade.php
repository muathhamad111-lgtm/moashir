@props(['frame', 'mail', 'heading', 'preheader' => '', 'reason' => null, 'summary' => null, 'category' => null])
{{ $heading }}
@if ($summary)
{{ $summary }}
@endif

{{ $slot }}

{{ __('mail.sign_off') }}
{{ __('mail.team') }}

--
@if ($reason)
{{ __('mail.reasons.'.$reason->value) }}
@endif
{{ __('mail.no_reply') }}
{{ __('mail.contact') }}: {{ $frame->contactUrl }}
{{ __('mail.privacy') }}: {{ $frame->privacyUrl }}
{{ __('mail.terms') }}: {{ $frame->termsUrl }}
{{ __('mail.copyright', ['year' => $frame->year]) }}
