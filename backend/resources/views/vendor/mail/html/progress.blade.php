@props(['progress'])
<p class="mail-progress-heading">{{ __('mail.progress.heading') }}</p>
<table class="mail-progress" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($progress->stages() as $stage)
@php($state = $progress->stateOf($stage))
<tr>
<td class="mail-step-marker" width="48" align="center" valign="top"><span class="mail-step-dot mail-step-dot-{{ $state->value }}">{{ $state->isDone() ? '✓' : $loop->iteration }}</span></td>
<td class="mail-step mail-step-{{ $state->value }}" valign="top">{{ __('mail.progress.stages.'.$stage->value) }}@if ($state->isLabelled()) <span class="mail-step-state mail-step-state-{{ $state->value }}">· {{ __('mail.progress.states.'.$state->value) }}</span>@endif</td>
</tr>
@endforeach
</table>
