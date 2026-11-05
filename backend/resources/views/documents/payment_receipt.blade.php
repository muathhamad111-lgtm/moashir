@extends('documents.layout')

@php
    $labelsAr = $data['labels']['ar'] ?? [];
    $labelsEn = $data['labels']['en'] ?? [];
    $currencyAr = $data['currency_label']['ar'] ?? '';
    $currencyEn = $data['currency_label']['en'] ?? '';
    $rows = [
        ['ar' => $labelsAr['payment_reference'] ?? '', 'en' => $labelsEn['payment_reference'] ?? '', 'value' => $data['payment_reference'] ?? '', 'latin' => true],
        ['ar' => $labelsAr['entity'] ?? '', 'en' => $labelsEn['entity'] ?? '', 'value' => $data['entity_name']['ar'] ?? '', 'secondary' => $data['entity_name']['en'] ?? null],
        ['ar' => $labelsAr['service'] ?? '', 'en' => $labelsEn['service'] ?? '', 'value' => $data['purpose_label']['ar'] ?? '', 'secondary' => $data['purpose_label']['en'] ?? null],
        ['ar' => __('documents.receipt.price_before_vat', [], 'ar'), 'en' => __('documents.receipt.price_before_vat', [], 'en'), 'value' => ($data['price_before_vat'] ?? '').' '.$currencyAr, 'secondary' => ($data['price_before_vat'] ?? '').' '.$currencyEn, 'amount' => true],
        ['ar' => __('documents.receipt.vat_rate', [], 'ar'), 'en' => __('documents.receipt.vat_rate', [], 'en'), 'value' => ($data['vat_rate'] ?? '').'%', 'latin' => true],
        ['ar' => __('documents.receipt.vat', [], 'ar'), 'en' => __('documents.receipt.vat', [], 'en'), 'value' => ($data['vat_amount'] ?? '').' '.$currencyAr, 'secondary' => ($data['vat_amount'] ?? '').' '.$currencyEn, 'amount' => true],
        ['ar' => $labelsAr['total'] ?? '', 'en' => $labelsEn['total'] ?? '', 'value' => ($data['total'] ?? '').' '.$currencyAr, 'secondary' => ($data['total'] ?? '').' '.$currencyEn, 'amount' => true, 'strong' => true],
        ['ar' => $labelsAr['method'] ?? '', 'en' => $labelsEn['method'] ?? '', 'value' => $data['method_label']['ar'] ?? '', 'secondary' => isset($data['card_last4']) ? '•••• '.$data['card_last4'] : null],
        ['ar' => $labelsAr['paid_at'] ?? '', 'en' => $labelsEn['paid_at'] ?? '', 'value' => $data['paid_on'] ?? '', 'latin' => true],
    ];
@endphp

@section('title', ($labelsAr['title'] ?? '').' — '.($data['number'] ?? $data['payment_reference'] ?? ''))

@section('styles')
@page { size: 210mm 297mm; margin: 16mm 16mm 18mm; }
.receipt__head { display: flex; align-items: center; justify-content: space-between; padding-bottom: 5mm; border-bottom: 0.6mm solid var(--color-primary); }
.receipt__logo { height: 20mm; }
.receipt__title { text-align: end; }
.receipt__title h1 { font-weight: 900; font-size: 22pt; }
.receipt__title p { margin: 0; font-family: var(--font-latin); font-weight: 600; font-size: 11pt; color: var(--color-teal-deep); }
.receipt__meta { display: flex; justify-content: space-between; gap: 6mm; margin: 5mm 0; padding: 3.5mm 5mm; background: var(--color-surface); border: 0.3mm solid var(--color-border); border-radius: 2mm; font-size: 9.5pt; }
.receipt__meta strong { display: block; font-size: 11pt; }
.receipt__meta .label-en { font-family: var(--font-latin); font-size: 8pt; color: var(--color-muted); }
.receipt__table { width: 100%; border-collapse: collapse; font-size: 10pt; }
.receipt__table th, .receipt__table td { padding: 2.6mm 3mm; border-bottom: 0.25mm solid var(--color-border); vertical-align: top; }
.receipt__table th { width: 30%; font-weight: 600; text-align: start; }
.receipt__table th.label-en { text-align: end; font-family: var(--font-latin); font-weight: 500; font-size: 8.8pt; color: var(--color-muted); }
.receipt__table td { text-align: center; }
.receipt__table td span { display: block; font-family: var(--font-latin); font-size: 8.6pt; color: var(--color-muted); }
.receipt__table tr.is-strong th, .receipt__table tr.is-strong td { background: var(--color-secondary); font-weight: 700; font-size: 11pt; }
.receipt__notice { margin: 6mm 0 0; padding: 3mm 5mm; border-inline-start: 1.4mm solid var(--color-bronze); background: var(--color-surface); font-weight: 600; }
.receipt__notice span { display: block; font-family: var(--font-latin); font-weight: 500; font-size: 9pt; }
.receipt__footer { margin-top: 10mm; text-align: center; font-size: 8.8pt; color: var(--color-muted); }
.receipt__footer span { display: block; font-family: var(--font-latin); }
@endsection

@section('content')
<header class="receipt__head">
  <img class="receipt__logo" src="{{ $assets->logo() }}" alt="{{ __('documents.logo_alt', [], 'ar') }}">
  <div class="receipt__title">
    <h1>{{ $labelsAr['title'] ?? '' }}</h1>
    <p lang="en" dir="ltr">{{ $labelsEn['title'] ?? '' }}</p>
  </div>
</header>

<section class="receipt__meta">
  <div>
    {{ $labelsAr['number'] ?? '' }} <span class="label-en">· {{ $labelsEn['number'] ?? '' }}</span>
    <strong class="ltr latin">{{ $data['number'] ?? '—' }}</strong>
  </div>
  <div>
    {{ $labelsAr['issued_on'] ?? '' }} <span class="label-en">· {{ $labelsEn['issued_on'] ?? '' }}</span>
    <strong>@include('documents.partials.dates', ['gregorian' => $data['issued_on'] ?? '', 'hijri' => $data['issued_on_hijri']['ar'] ?? '', 'locale' => 'ar'])</strong>
  </div>
</section>

<table class="receipt__table">
  <tbody>
    @foreach ($rows as $row)
      <tr class="{{ ($row['strong'] ?? false) ? 'is-strong' : '' }}">
        <th scope="row">{{ $row['ar'] }}</th>
        <td>
          <bdi class="{{ ($row['latin'] ?? false) || ($row['amount'] ?? false) ? 'latin' : '' }}">{{ $row['value'] }}</bdi>
          @if (($row['secondary'] ?? null) !== null && $row['secondary'] !== $row['value'])
            <span lang="en" dir="ltr">{{ $row['secondary'] }}</span>
          @endif
        </td>
        <th scope="row" class="label-en" lang="en" dir="ltr">{{ $row['en'] }}</th>
      </tr>
    @endforeach
  </tbody>
</table>

@if (($data['is_test'] ?? false) === true)
  <p class="receipt__notice">{{ $labelsAr['test_notice'] ?? '' }}<span lang="en" dir="ltr">{{ $labelsEn['test_notice'] ?? '' }}</span></p>
@endif
@if (($data['status'] ?? null) === 'voided')
  <p class="receipt__notice">{{ $labelsAr['void_notice'] ?? '' }}<span lang="en" dir="ltr">{{ $labelsEn['void_notice'] ?? '' }}</span></p>
@endif

<footer class="receipt__footer">
  {{ $labelsAr['footer'] ?? '' }}
  <span lang="en" dir="ltr">{{ $labelsEn['footer'] ?? '' }}</span>
</footer>
@endsection
