@props(['url'])
@aware(['frame'])
<table class="mail-action" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="mail-action-cell" align="{{ $frame->align }}">
<table class="mail-button-table" role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="mail-button" align="center"><a class="mail-button-link" href="{{ $url }}" target="_blank" rel="noopener">{{ $slot }}</a></td>
</tr>
</table>
</td>
</tr>
</table>
<p class="mail-link-hint">{{ __('mail.link_hint') }}</p>
<p class="mail-link" dir="ltr"><a class="mail-link-anchor" href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></p>
