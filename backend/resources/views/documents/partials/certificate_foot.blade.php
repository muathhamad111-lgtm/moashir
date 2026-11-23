      <footer class="certificate__foot">
        {{-- Three rows: Arabic label at the right edge, the value centred, English label at the left edge (operator 2026-11-22). --}}
        <table class="certificate__facts">
          <tr>
            <th scope="row" class="certificate__facts-ar">{{ __('documents.certificate.number', [], 'ar') }}</th>
            <td class="ltr latin">{{ $data['number'] ?? '' }}</td>
            <th class="certificate__facts-en" lang="en" dir="ltr">{{ __('documents.certificate.number', [], 'en') }}</th>
          </tr>
          <tr>
            <th scope="row" class="certificate__facts-ar">{{ __('documents.certificate.request', [], 'ar') }}</th>
            <td class="ltr latin">{{ $data['request_number'] ?? '' }}</td>
            <th class="certificate__facts-en" lang="en" dir="ltr">{{ __('documents.certificate.request', [], 'en') }}</th>
          </tr>
          <tr>
            <th scope="row" class="certificate__facts-ar">{{ __('documents.certificate.issued_on', [], 'ar') }}</th>
            <td>@include('documents.partials.dates', ['gregorian' => $data['issued_on'] ?? '', 'hijri' => $data['issued_on_hijri']['ar'] ?? '', 'locale' => 'ar'])</td>
            <th class="certificate__facts-en" lang="en" dir="ltr">{{ __('documents.certificate.issued_on', [], 'en') }}</th>
          </tr>
        </table>
        <p class="certificate__note">
          {{ __('documents.certificate.issued_electronically', [], 'ar') }}
          <span lang="en" dir="ltr">{{ __('documents.certificate.issued_electronically', [], 'en') }}</span>
          @if (($data['is_test'] ?? false) === true)
            <strong class="certificate__unverifiable">{{ __('documents.certificate.not_valid_for_verification', [], 'ar') }}<span lang="en" dir="ltr">{{ __('documents.certificate.not_valid_for_verification', [], 'en') }}</span></strong>
          @endif
        </p>
        <figure class="certificate__qr">
          <img src="{{ $qrCodes->dataUri((string) ($data['verification_url'] ?? '')) }}" alt="{{ __('documents.qr_alt', [], 'ar') }}">
          <figcaption>{{ __('documents.certificate.verify_hint', [], 'ar') }}<span lang="en" dir="ltr">{{ __('documents.certificate.verify_hint', [], 'en') }}</span></figcaption>
        </figure>
      </footer>
