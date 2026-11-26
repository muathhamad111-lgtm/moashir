@props(['progress'])
{{ __('mail.progress.heading') }}
@foreach ($progress->stages() as $stage)
@php($state = $progress->stateOf($stage))
{{ $state->isDone() ? '✓' : $loop->iteration.'.' }} {{ __('mail.progress.stages.'.$stage->value) }}{{ $state->isLabelled() ? ' ('.__('mail.progress.states.'.$state->value).')' : '' }}
@endforeach

