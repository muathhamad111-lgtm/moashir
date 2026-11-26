@props(['rows'])
<table class="mail-details" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($rows as $row)
<tr>
<td class="mail-detail-label{{ $loop->first ? '' : ' mail-detail-next' }}" width="38%" valign="top">{{ $row->label }}</td>
<td class="mail-detail-value mail-detail-{{ $row->tone->value }}{{ $loop->first ? '' : ' mail-detail-next' }}" valign="top">@if ($row->isReference())<bdi dir="ltr">{{ $row->value }}</bdi>@else{{ $row->value }}@endif</td>
</tr>
@endforeach
</table>
