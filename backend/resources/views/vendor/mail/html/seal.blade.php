@props(['seal', 'frame', 'mail'])
<table class="mail-seal mail-seal-{{ $seal->level->value }}" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="mail-seal-cell" align="center">
<img class="mail-seal-image" src="{{ $mail->embed($frame->sealPath($seal->level)) }}" width="76" height="91" alt="">
<p class="mail-seal-level">{{ __('certificates.levels.'.$seal->level->value) }}</p>
<p class="mail-seal-caption">{{ __('mail.seal.caption') }}</p>
</td>
</tr>
</table>
