@if ($page->watermark !== null)
<div class="watermark" aria-hidden="true"><span>{{ __('documents.watermarks.'.$page->watermark->value, [], 'ar') }} — {{ __('documents.watermarks.'.$page->watermark->value, [], 'en') }}</span></div>
@endif
