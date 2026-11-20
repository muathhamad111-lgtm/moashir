@props(['items'])
<p class="mail-items-heading">{{ __('mail.requested_items.heading', ['count' => $items->count()]) }}</p>
<table class="mail-items" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($items->shown() as $item)
<tr>
<td class="mail-item-index{{ $loop->first ? '' : ' mail-item-next' }}" width="52" align="center" valign="top"><span class="mail-item-number">{{ $loop->iteration }}</span></td>
<td class="mail-item{{ $loop->first ? '' : ' mail-item-next' }}" valign="top">
<p class="mail-item-title"><bdi dir="ltr">{{ $item->code }}</bdi>{{ $item->name ? ' '.$item->name->in(app()->getLocale()) : '' }}</p>
<p class="mail-item-kind">{{ __('mail.requested_items.kinds.'.$item->kind->value) }}</p>
@if ($item->hasNote())
<p class="mail-item-note">{{ $item->note }}</p>
@endif
</td>
</tr>
@endforeach
@if ($items->hiddenCount() > 0)
<tr>
<td class="mail-items-more mail-item-next" colspan="2">{{ trans_choice('mail.requested_items.more', $items->hiddenCount()) }}</td>
</tr>
@endif
</table>
