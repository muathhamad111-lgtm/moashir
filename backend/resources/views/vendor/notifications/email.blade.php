<x-mail::message :frame="$frame" :mail="$message" :heading="$title ?? $subject" :preheader="$introLines[0] ?? ''" :reason="$reason ?? null">
<x-mail::paragraph>{{ $greeting ?: __('mail.greeting.generic') }}</x-mail::paragraph>
@foreach ($introLines as $line)
<x-mail::paragraph>{{ $line }}</x-mail::paragraph>
@endforeach
@foreach ($quotedLines ?? [] as $line)
<x-mail::quote>{{ $line }}</x-mail::quote>
@endforeach
@isset($code)
<x-mail::code>{{ $code }}</x-mail::code>
@endisset
@isset($actionText)
<x-mail::action :url="$actionUrl">{{ $actionText }}</x-mail::action>
@endisset
@foreach ($outroLines as $line)
<x-mail::paragraph>{{ $line }}</x-mail::paragraph>
@endforeach
</x-mail::message>
