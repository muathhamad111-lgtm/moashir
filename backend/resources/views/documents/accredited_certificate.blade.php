@extends('documents.layout')

@php
    $level = $data['level'] ?? null;
    $titleKey = $level === null ? 'documents.certificate.participation_title' : 'documents.certificate.title';
    $entityAr = $data['entity_name']['ar'] ?? '';
    $entityEn = $data['entity_name']['en'] ?? null;
    $entityLogo = is_string($data['entity_logo'] ?? null) && str_starts_with($data['entity_logo'], 'data:image/') ? $data['entity_logo'] : null;
@endphp

@section('title', __($titleKey, [], 'ar').' — '.($data['number'] ?? ''))

@section('styles')
@page { size: 297mm 210mm; margin: 0; }
.certificate { position: relative; width: 297mm; height: 210mm; box-sizing: border-box; padding: 8mm; overflow: hidden; }
.certificate__frame { height: 100%; box-sizing: border-box; border: 2.6mm solid var(--color-dark); padding: 1.8mm; }
.certificate__inner { position: relative; height: 100%; box-sizing: border-box; border: 0.45mm solid var(--color-gold); padding: 8mm 16mm 6mm; display: flex; flex-direction: column; background: radial-gradient(circle at 50% 0%, var(--color-surface) 0%, var(--color-white) 55%); }
.certificate__head { display: flex; align-items: flex-start; justify-content: space-between; min-height: 30mm; }
.certificate__logo { height: 24mm; }
.certificate__seal { position: relative; width: 40mm; margin-top: -2mm; }
.certificate__seal img { display: block; width: 100%; }
.certificate__seal-year { position: absolute; left: 0; width: 98.9%; top: 71.9%; transform: translateY(-50%); text-align: center; font-family: var(--font-latin); font-weight: 700; font-size: 3.3mm; letter-spacing: 0.25mm; line-height: 1; }
.certificate__star { width: 18mm; opacity: 0.9; }
.certificate--bronze .certificate__seal-year { color: var(--color-bronze); }
.certificate--silver .certificate__seal-year { color: var(--color-silver); }
.certificate--gold .certificate__seal-year { color: var(--color-gold); }
.certificate--diamond .certificate__seal-year { color: var(--color-diamond); }
.certificate__body { flex: 1; display: flex; flex-direction: column; justify-content: center; text-align: center; margin-top: -16mm; padding-bottom: 4mm; }
.certificate__title { font-weight: 900; font-size: 32pt; line-height: 1.25; color: var(--color-dark); }
.certificate__rule { width: 46mm; height: 0.5mm; margin: 0 auto 3.5mm; background: var(--color-gold); }
.certificate__title-en { margin: 0.5mm 0 3mm; font-family: var(--font-latin); font-weight: 600; font-size: 12pt; letter-spacing: 1.2pt; text-transform: uppercase; color: var(--color-teal-deep); }
.certificate__lead { margin: 0; font-size: 13pt; }
.certificate__entity { margin: 2mm 0 0; font-family: var(--font-heading); font-weight: 700; font-size: 25pt; line-height: 1.3; }
.certificate__entity-row { display: flex; align-items: center; justify-content: center; gap: 5mm; margin-top: 2mm; }
.certificate__entity-row .certificate__entity { margin: 0; }
.certificate__entity-logo { flex: none; max-width: 36mm; max-height: 18mm; object-fit: contain; }
.certificate__entity-en { margin: 0 0 2mm; font-family: var(--font-latin); font-size: 11.5pt; color: var(--color-teal-deep); }
.certificate__statement { margin: 2mm auto 0; max-width: 215mm; font-size: 13.5pt; }
.certificate__statement strong { font-weight: 700; }
.certificate__percent { font-family: var(--font-latin); font-weight: 700; font-size: 14pt; color: var(--color-accent-text); }
.certificate__statement-en { margin: 2mm auto 0; max-width: 220mm; font-family: var(--font-latin); font-size: 10pt; color: var(--color-muted); }
.certificate__foot { display: flex; align-items: flex-end; justify-content: space-between; gap: 8mm; border-top: 0.3mm solid var(--color-border); padding-top: 3mm; }
.certificate__facts { margin: 0; display: grid; grid-template-columns: auto auto; column-gap: 4mm; row-gap: 0.6mm; font-size: 8.6pt; }
.certificate__facts dt { color: var(--color-muted); }
.certificate__facts dd { margin: 0; font-weight: 600; }
.certificate__facts .label-en { font-family: var(--font-latin); font-size: 7.4pt; }
.certificate__note { flex: 1; margin: 0; text-align: center; font-size: 8.4pt; color: var(--color-muted); }
.certificate__note span { display: block; font-family: var(--font-latin); font-size: 7.4pt; }
.certificate__test { display: block; margin-top: 1mm; color: var(--color-bronze-text); font-weight: 700; }
.certificate__qr { margin: 0; width: 31mm; text-align: center; }
.certificate__qr img { width: 24mm; height: 24mm; }
.certificate__qr figcaption { font-size: 7pt; line-height: 1.35; color: var(--color-muted); }
.certificate__qr figcaption span { display: block; font-family: var(--font-latin); }
@endsection

@section('content')
<div class="certificate certificate--{{ $level ?? 'participation' }}">
  <div class="certificate__frame">
    <div class="certificate__inner">
      <header class="certificate__head">
        <img class="certificate__logo" src="{{ $assets->logo() }}" alt="{{ __('documents.logo_alt', [], 'ar') }}">
        @if ($level !== null)
          <div class="certificate__seal">
            <img src="{{ $assets->seal($level) }}" alt="{{ __('documents.certificate.seal_alt', ['level' => $data['level_label']['ar'] ?? ''], 'ar') }}">
            <span class="certificate__seal-year" aria-hidden="true">{{ $data['issue_year'] ?? '' }}</span>
          </div>
        @else
          <img class="certificate__star" src="{{ $assets->star() }}" alt="">
        @endif
      </header>

      <main class="certificate__body">
        <h1 class="certificate__title">{{ __($titleKey, [], 'ar') }}</h1>
        <p class="certificate__title-en" lang="en" dir="ltr">{{ __($titleKey, [], 'en') }}</p>
        <div class="certificate__rule" aria-hidden="true"></div>
        <p class="certificate__lead">{{ __('documents.certificate.certifies', [], 'ar') }}</p>
        <div @class(['certificate__entity-row' => $entityLogo !== null])>
          @if ($entityLogo !== null)
            <img class="certificate__entity-logo" src="{{ $entityLogo }}" alt="{{ __('documents.certificate.entity_logo_alt', ['entity' => $entityAr], 'ar') }}">
          @endif
          <p class="certificate__entity">{{ $entityAr }}</p>
        </div>
        @if ($entityEn !== null)
          <p class="certificate__entity-en" lang="en" dir="ltr">{{ $entityEn }}</p>
        @endif
        <p class="certificate__statement">
          {{ __('documents.certificate.completed', [], 'ar') }}
          {{ __('documents.certificate.achieved', [], 'ar') }}
          <bdi class="certificate__percent ltr" dir="ltr">{{ $data['percent'] ?? '' }}%</bdi>@if ($level !== null)،
          {{ __('documents.certificate.level', [], 'ar') }} <strong>{{ $data['level_label']['ar'] ?? '' }}</strong>@endif.
        </p>
        <p class="certificate__statement-en" lang="en" dir="ltr">
          {{ __('documents.certificate.certifies', [], 'en') }} <bdi>{{ $entityEn ?? $entityAr }}</bdi>
          {{ __('documents.certificate.completed', [], 'en') }} {{ __('documents.certificate.achieved', [], 'en') }}
          {{ $data['percent'] ?? '' }}%@if ($level !== null), {{ __('documents.certificate.level', [], 'en') }} {{ $data['level_label']['en'] ?? '' }}@endif.
        </p>
      </main>

      <footer class="certificate__foot">
        <dl class="certificate__facts">
          <dt>{{ __('documents.certificate.number', [], 'ar') }} <span class="label-en">· {{ __('documents.certificate.number', [], 'en') }}</span></dt>
          <dd class="ltr latin">{{ $data['number'] ?? '' }}</dd>
          <dt>{{ __('documents.certificate.verification_code', [], 'ar') }} <span class="label-en">· {{ __('documents.certificate.verification_code', [], 'en') }}</span></dt>
          <dd class="ltr latin">{{ $data['verification_code'] ?? '' }}</dd>
          <dt>{{ __('documents.certificate.issued_on', [], 'ar') }} <span class="label-en">· {{ __('documents.certificate.issued_on', [], 'en') }}</span></dt>
          <dd>@include('documents.partials.dates', ['gregorian' => $data['issued_on'] ?? '', 'hijri' => $data['issued_on_hijri']['ar'] ?? '', 'locale' => 'ar'])</dd>
          <dt>{{ __('documents.certificate.request', [], 'ar') }} <span class="label-en">· {{ __('documents.certificate.request', [], 'en') }}</span></dt>
          <dd class="ltr latin">{{ $data['request_number'] ?? '' }}</dd>
        </dl>
        <p class="certificate__note">
          {{ __('documents.certificate.issued_electronically', [], 'ar') }}
          <span lang="en" dir="ltr">{{ __('documents.certificate.issued_electronically', [], 'en') }}</span>
          @if (($data['is_test'] ?? false) === true)
            <strong class="certificate__test">{{ __('documents.certificate.test_notice', [], 'ar') }}</strong>
          @endif
        </p>
        <figure class="certificate__qr">
          <img src="{{ $qrCodes->dataUri((string) ($data['verification_url'] ?? '')) }}" alt="{{ __('documents.qr_alt', [], 'ar') }}">
          <figcaption>{{ __('documents.certificate.verify_hint', [], 'ar') }}<span lang="en" dir="ltr">{{ __('documents.certificate.verify_hint', [], 'en') }}</span></figcaption>
        </figure>
      </footer>
    </div>
  </div>
</div>
@endsection
