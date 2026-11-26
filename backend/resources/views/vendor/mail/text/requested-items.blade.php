@props(['items'])
{{ __('mail.requested_items.heading', ['count' => $items->count()]) }}
@foreach ($items->shown() as $item)
{{ $loop->iteration }}. {{ $item->code }}{{ $item->name ? ' '.$item->name->in(app()->getLocale()) : '' }} ({{ __('mail.requested_items.kinds.'.$item->kind->value) }})
@if ($item->hasNote())
   {{ $item->note }}
@endif
@endforeach
@if ($items->hiddenCount() > 0)
{{ trans_choice('mail.requested_items.more', $items->hiddenCount()) }}
@endif

