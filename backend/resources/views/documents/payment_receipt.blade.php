@extends('documents.layout')

@use('App\Support\GroupedAmount')

@php
    $ar = $data['labels']['ar'] ?? [];
    $en = $data['labels']['en'] ?? [];
    $isVoided = ($data['status'] ?? null) === 'voided';
    $status = $isVoided ? 'voided' : 'issued';
    $rate = (string) ($data['vat_rate'] ?? '');
    $rate = str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
    $hasCard = ($data['card_last4'] ?? null) !== null;
    $hasBank = isset($data['bank_name']['ar']);
    $gatewayReference = $data['provider_payment_id'] ?? null;
@endphp

@section('title', ($ar['title'] ?? '').' — '.($data['number'] ?? $data['payment_reference'] ?? ''))

@section('styles')
@page { size: 210mm 297mm; margin: 14mm 16mm 14mm; }
.watermark span { font-size: 64pt; }
.rc { display: flex; flex-direction: column; min-height: 262mm; }
.en { font-family: var(--font-latin); font-weight: 500; color: var(--color-muted); }
/* English lines sit under their Arabic label, so they follow the Arabic (right) edge of this RTL document. */
.rc [dir="ltr"] { text-align: right; }
.rc-head__title [dir="ltr"], .rc-num [dir="ltr"], .rc-foot__ref [dir="ltr"] { text-align: left; }
.rc-head { display: flex; align-items: flex-end; justify-content: space-between; padding-bottom: 3mm; }
.rc-head__logo { height: 16mm; }
.rc-head__title { text-align: end; }
.rc-head__title h1 { font-weight: 900; font-size: 21pt; line-height: 1.2; color: var(--color-teal-deep); }
.rc-head__title > .en { margin: 0 0 2mm; font-size: 11pt; font-weight: 600; color: var(--color-teal-deep); }
.rc-status { display: inline-block; padding: 0.8mm 3.5mm; border: 0.3mm solid var(--color-teal-deep); border-radius: 10mm; font-size: 8.8pt; font-weight: 700; color: var(--color-teal-deep); }
.rc-status .en { font-size: 8.4pt; font-weight: 600; color: inherit; }
.rc-status--voided { border-color: var(--color-bronze-text); color: var(--color-bronze-text); }
.rc-rule { height: 0.9mm; background: var(--color-teal-deep); }
.rc-rule::after { content: ''; display: block; height: 0.35mm; margin-top: 0.9mm; background: var(--color-gold); }
.rc-facts { display: grid; grid-template-columns: repeat(var(--facts), 1fr); margin: 4.5mm 0; border: 0.3mm solid var(--color-border); border-radius: 2mm; background: var(--color-surface); }
.rc-fact { padding: 2.6mm 4mm; border-inline-start: 0.3mm solid var(--color-border); }
.rc-fact:first-child { border-inline-start: 0; }
.rc-label { display: block; font-size: 8.6pt; font-weight: 600; color: var(--color-teal-deep); line-height: 1.3; }
.rc-label .en { display: block; font-size: 7.4pt; font-weight: 500; }
.rc-fact__value { display: block; margin-top: 1.5mm; font-size: 10.5pt; white-space: nowrap; font-weight: 700; line-height: 1.35; }
.rc-fact__value small { display: block; font-size: 8.6pt; font-weight: 500; }
.rc-fact__value--none { color: var(--color-muted); }
.rc-cards { display: grid; grid-template-columns: 1.3fr 1fr; gap: 4mm; }
.rc-card { padding: 3mm 4mm; border: 0.3mm solid var(--color-border); border-radius: 2mm; }
.rc-card__value { margin: 1.5mm 0 0; font-size: 11pt; font-weight: 700; line-height: 1.4; }
.rc-card__value .en { display: block; font-size: 9pt; }
.rc-card__meta { display: flex; justify-content: space-between; align-items: center; margin: 2.5mm 0 0; padding-top: 2.5mm; border-top: 0.25mm dashed var(--color-border); }
.rc-card__meta .rc-code { font-size: 10.5pt; font-weight: 700; }
.rc-section { margin-top: 4.5mm; }
.rc-section h2 { display: flex; align-items: baseline; gap: 2.5mm; margin-bottom: 2.5mm; font-size: 11.5pt; font-weight: 800; color: var(--color-teal-deep); }
.rc-section h2 .en { font-size: 9pt; font-weight: 600; }
.rc-amounts { width: 100%; border-collapse: collapse; font-size: 10pt; }
.rc-amounts th, .rc-amounts td { padding: 2.2mm 4mm; border-bottom: 0.25mm solid var(--color-border); text-align: start; vertical-align: middle; }
.rc-amounts thead th { background: var(--color-teal-deep); color: var(--color-white); font-size: 9pt; font-weight: 700; }
.rc-amounts thead th .en { display: block; color: var(--color-secondary); font-size: 7.6pt; }
.rc-amounts .rc-num { width: 38%; text-align: end; font-family: var(--font-latin); font-variant-numeric: tabular-nums; font-weight: 600; }
.rc-amounts tbody th { font-weight: 600; }
.rc-amounts tbody th .en { display: block; font-size: 8.4pt; }
.rc-amounts tfoot th, .rc-amounts tfoot td { padding: 2.8mm 4mm; border-top: 0.8mm solid var(--color-teal-deep); border-bottom: 0; background: var(--color-secondary); font-size: 12pt; font-weight: 800; }
.rc-amounts tfoot th .en { display: block; font-size: 9pt; font-weight: 600; color: var(--color-teal-deep); }
.rc-amounts tfoot .rc-num { font-size: 15pt; font-weight: 800; color: var(--color-teal-deep); }
.rc-currency { font-size: 9pt; font-weight: 600; margin-inline-start: 1.5mm; }
.rc-details { display: grid; grid-template-columns: 1fr 1fr; border-top: 0.25mm solid var(--color-border); }
.rc-detail { padding: 2.2mm 0; border-bottom: 0.25mm solid var(--color-border); }
.rc-detail:nth-child(odd) { padding-inline-end: 4mm; }
.rc-detail:nth-child(even) { padding-inline-start: 4mm; border-inline-start: 0.25mm solid var(--color-border); }
.rc-detail__value { display: block; margin-top: 1mm; font-size: 10.5pt; font-weight: 700; }
.rc-detail__value .en { font-size: 8.8pt; }
.rc-detail__value small { display: block; font-size: 8.6pt; font-weight: 500; }
.rc-detail__value--code { font-size: 9.4pt; }
.rc-code { font-family: var(--font-latin); font-weight: 600; letter-spacing: 0.2pt; }
.rc-notice { margin: 4mm 0 0; padding: 3mm 5mm; border: 0.3mm solid var(--color-bronze); border-inline-start-width: 1.6mm; border-radius: 1.5mm; font-weight: 700; color: var(--color-bronze-text); }
.rc-notice .en { display: block; font-size: 9pt; color: var(--color-bronze-text); }
.rc-foot { margin-top: auto; padding-top: 3mm; border-top: 0.3mm solid var(--color-border); display: flex; justify-content: space-between; align-items: flex-end; gap: 6mm; font-size: 8.6pt; color: var(--color-muted); }
.rc-foot p { margin: 0; }
.rc-foot .en { display: block; font-size: 8pt; }
.rc-foot__ref { text-align: end; white-space: nowrap; }
@endsection

@section('content')
<div class="rc">
  <header class="rc-head">
    <img class="rc-head__logo" src="{{ $assets->logo() }}" alt="{{ __('documents.logo_alt', [], 'ar') }}">
    <div class="rc-head__title">
      <h1>{{ $ar['title'] ?? '' }}</h1>
      <p class="en" lang="en" dir="ltr">{{ $en['title'] ?? '' }}</p>
      <span class="rc-status rc-status--{{ $status }}">{{ $ar['statuses'][$status] ?? '' }} · <span class="en" lang="en">{{ $en['statuses'][$status] ?? '' }}</span></span>
    </div>
  </header>
  <div class="rc-rule" aria-hidden="true"></div>

  <section class="rc-facts" style="--facts: {{ ($data['vat_registration_number'] ?? null) !== null ? 4 : 3 }}">
    <div class="rc-fact">
      <span class="rc-label">{{ $ar['number'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['number'] ?? '' }}</span></span>
      @if (($data['number'] ?? null) !== null)
        <bdi class="rc-fact__value rc-code" dir="ltr">{{ $data['number'] }}</bdi>
      @else
        <span class="rc-fact__value rc-fact__value--none">{{ $ar['no_number'] ?? '' }}<small class="en" lang="en" dir="ltr">{{ $en['no_number'] ?? '' }}</small></span>
      @endif
    </div>
    <div class="rc-fact">
      <span class="rc-label">{{ $ar['issued_on'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['issued_on'] ?? '' }}</span></span>
      <span class="rc-fact__value"><bdi class="rc-code" dir="ltr">{{ $data['issued_on'] ?? '' }}</bdi> {{ __('documents.gregorian', [], 'ar') }}<small>{{ $data['issued_on_hijri']['ar'] ?? '' }} {{ __('documents.hijri', [], 'ar') }}</small></span>
    </div>
    <div class="rc-fact">
      <span class="rc-label">{{ $ar['payment_reference'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['payment_reference'] ?? '' }}</span></span>
      <bdi class="rc-fact__value rc-code" dir="ltr">{{ $data['payment_reference'] ?? '' }}</bdi>
    </div>
    @if (($data['vat_registration_number'] ?? null) !== null)
      <div class="rc-fact">
        <span class="rc-label">{{ $ar['vat_registration_number'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['vat_registration_number'] ?? '' }}</span></span>
        <bdi class="rc-fact__value rc-code" dir="ltr">{{ $data['vat_registration_number'] }}</bdi>
      </div>
    @endif
  </section>

  <section class="rc-cards">
    <div class="rc-card">
      <span class="rc-label">{{ $ar['payer'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['payer'] ?? '' }}</span></span>
      <p class="rc-card__value">{{ $data['entity_name']['ar'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $data['entity_name']['en'] ?? '' }}</span></p>
      @if (($data['entity_reference'] ?? null) !== null)
        <p class="rc-card__meta"><span class="rc-label">{{ $ar['entity_reference'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['entity_reference'] ?? '' }}</span></span><bdi class="rc-code" dir="ltr">{{ $data['entity_reference'] }}</bdi></p>
      @endif
    </div>
    <div class="rc-card">
      <span class="rc-label">{{ $ar['service'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['service'] ?? '' }}</span></span>
      <p class="rc-card__value">{{ $data['purpose_label']['ar'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $data['purpose_label']['en'] ?? '' }}</span></p>
    </div>
  </section>

  <section class="rc-section">
    <h2>{{ $ar['amounts'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['amounts'] ?? '' }}</span></h2>
    <table class="rc-amounts">
      <thead>
        <tr>
          <th scope="col">{{ $ar['description'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['description'] ?? '' }}</span></th>
          <th scope="col" class="rc-num">{{ $ar['amount'] ?? '' }} ({{ $data['currency_label']['ar'] ?? '' }})<span class="en" lang="en" dir="ltr">{{ $en['amount'] ?? '' }} ({{ $data['currency_label']['en'] ?? '' }})</span></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <th scope="row">{{ $ar['price_before_vat'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['price_before_vat'] ?? '' }}</span></th>
          <td class="rc-num"><bdi dir="ltr">{{ GroupedAmount::format((string) ($data['price_before_vat'] ?? '')) }}</bdi></td>
        </tr>
        <tr>
          <th scope="row">{{ trans('finance.receipt.vat', ['rate' => $rate], 'ar') }}<span class="en" lang="en" dir="ltr">{{ trans('finance.receipt.vat', ['rate' => $rate], 'en') }}</span></th>
          <td class="rc-num"><bdi dir="ltr">{{ GroupedAmount::format((string) ($data['vat_amount'] ?? '')) }}</bdi></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th scope="row">{{ $ar['total'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['total'] ?? '' }}</span></th>
          <td class="rc-num"><bdi dir="ltr">{{ GroupedAmount::format((string) ($data['total'] ?? '')) }}</bdi><span class="rc-currency">{{ $data['currency_label']['ar'] ?? '' }}</span></td>
        </tr>
      </tfoot>
    </table>
  </section>

  <section class="rc-section">
    <h2>{{ $ar['payment'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['payment'] ?? '' }}</span></h2>
    <div class="rc-details">
      <div class="rc-detail">
        <span class="rc-label">{{ $ar['method'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['method'] ?? '' }}</span></span>
        <span class="rc-detail__value">{{ $data['method_label']['ar'] ?? '—' }}@if (($data['method_label']['en'] ?? null) !== null && $data['method_label']['en'] !== ($data['method_label']['ar'] ?? null))<span class="en"> · </span><span class="en" lang="en" dir="ltr">{{ $data['method_label']['en'] }}</span>@endif</span>
      </div>
      @if ($hasCard)
        <div class="rc-detail">
          <span class="rc-label">{{ $ar['card'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['card'] ?? '' }}</span></span>
          <bdi class="rc-detail__value rc-code" dir="ltr">•••• {{ $data['card_last4'] }}</bdi>
        </div>
      @endif
      @if ($hasBank)
        <div class="rc-detail">
          <span class="rc-label">{{ $ar['bank'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['bank'] ?? '' }}</span></span>
          <span class="rc-detail__value">{{ $data['bank_name']['ar'] }}<small class="en" lang="en" dir="ltr">{{ $data['bank_name']['en'] ?? '' }}</small></span>
        </div>
      @endif
      <div class="rc-detail">
        <span class="rc-label">{{ $ar['paid_at'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['paid_at'] ?? '' }}</span></span>
        <span class="rc-detail__value"><bdi class="rc-code" dir="ltr">{{ $data['paid_on'] ?? '—' }}</bdi> {{ __('documents.gregorian', [], 'ar') }}@if (isset($data['paid_on_hijri']['ar']))<small>{{ $data['paid_on_hijri']['ar'] }} {{ __('documents.hijri', [], 'ar') }}</small>@endif</span>
      </div>
      @if ($gatewayReference !== null)
        <div class="rc-detail">
          <span class="rc-label">{{ $ar['gateway_reference'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['gateway_reference'] ?? '' }}</span></span>
          <bdi class="rc-detail__value rc-detail__value--code rc-code" dir="ltr">{{ $gatewayReference }}</bdi>
        </div>
      @endif
    </div>
  </section>

  @if ($isVoided)
    <p class="rc-notice">{{ $ar['void_notice'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['void_notice'] ?? '' }}</span></p>
  @endif

  <footer class="rc-foot">
    <p>{{ $ar['footer'] ?? '' }}<span class="en" lang="en" dir="ltr">{{ $en['footer'] ?? '' }}</span></p>
    <p class="rc-foot__ref"><bdi class="rc-code" dir="ltr">{{ $data['number'] ?? $data['payment_reference'] ?? '' }}</bdi></p>
  </footer>
</div>
@endsection
