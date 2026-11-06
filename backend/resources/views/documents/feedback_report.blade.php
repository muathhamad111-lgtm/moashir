@extends('documents.layout')

@php
    $locale = $page->locale;
    $text = static fn (?array $value): string => (string) ($value[$locale] ?? $value['ar'] ?? '');
    $entity = $data['entity'] ?? [];
    $certificate = $data['certificate'] ?? null;
@endphp

@section('title', __('documents.feedback_report.title').' — '.($data['number'] ?? ''))

@section('styles')
@page { size: 210mm 297mm; margin: 16mm 14mm 18mm; @bottom-center { content: "{{ __('documents.page') }} " counter(page) " {{ __('documents.of') }} " counter(pages); font-family: 'Moashir Latin', 'Moashir Body', sans-serif; font-size: 8.5pt; color: #607070; } }
.report__head { display: flex; align-items: center; justify-content: space-between; padding-bottom: 4mm; border-bottom: 0.6mm solid var(--color-primary); }
.report__logo { height: 17mm; }
.report__title h1 { font-weight: 900; font-size: 21pt; }
.report__title p { margin: 0; color: var(--color-teal-deep); font-weight: 600; }
.report__facts { display: grid; grid-template-columns: 1fr 1fr; gap: 1.6mm 8mm; margin: 5mm 0; font-size: 9.6pt; }
.report__facts div { display: flex; gap: 2mm; }
.report__facts dt { color: var(--color-muted); min-width: 34mm; }
.report__facts dd { margin: 0; font-weight: 600; }
.report__summary { display: flex; align-items: center; gap: 6mm; padding: 4mm 6mm; background: var(--color-dark); color: var(--color-white); border-radius: 2.5mm; }
.report__summary-percent { font-family: var(--font-latin); font-weight: 700; font-size: 24pt; color: var(--color-primary); }
.report__summary-label { font-size: 10pt; color: var(--color-secondary); }
.report__summary-level { margin-inline-start: auto; padding: 1.5mm 4mm; border: 0.3mm solid var(--color-primary); border-radius: 10mm; font-weight: 700; }
h2.report__section { margin: 7mm 0 2.5mm; padding-inline-start: 2.5mm; border-inline-start: 1.4mm solid var(--color-gold); font-weight: 700; font-size: 13.5pt; }
table.report__table { width: 100%; border-collapse: collapse; font-size: 9.3pt; }
.report__table thead { display: table-header-group; }
.report__table th { background: var(--color-dark); color: var(--color-secondary); font-weight: 600; padding: 1.8mm 2mm; text-align: start; }
.report__table td { padding: 1.6mm 2mm; border-bottom: 0.25mm solid var(--color-border); vertical-align: top; }
.report__table tbody tr:nth-child(even) td { background: var(--color-surface); }
.report__table tfoot td { font-weight: 700; background: var(--color-secondary); }
.report__table .num { text-align: center; white-space: nowrap; font-family: var(--font-latin); }
.report__criterion { margin: 6mm 0 2mm; break-after: avoid; font-weight: 700; font-size: 12pt; color: var(--color-teal-deep); }
.report__criterion bdi { font-family: var(--font-latin); }
.report__sub { margin: 0 0 3mm; padding: 3mm 4mm; border: 0.3mm solid var(--color-border); border-radius: 2mm; break-inside: avoid; }
.report__sub-head { display: flex; justify-content: space-between; gap: 4mm; font-weight: 700; }
.report__sub-head .num { font-family: var(--font-latin); color: var(--color-accent-text); white-space: nowrap; }
.report__field { margin-top: 1.6mm; }
.report__field h3 { font-family: var(--font-body); font-size: 9pt; font-weight: 700; color: var(--color-muted); }
.report__field p { margin: 0.4mm 0 0; white-space: pre-line; }
.report__field--strengths h3 { color: var(--color-accent-text); }
.report__field--improvement h3 { color: var(--color-bronze-text); }
.report__empty { margin: 1mm 0 0; color: var(--color-muted); font-size: 9pt; }
.report__assessor-summary { margin: 0; padding: 3mm 4mm; border: 0.3mm solid var(--color-border); border-radius: 2mm; white-space: pre-line; }
.report__footer { margin-top: 8mm; padding-top: 3mm; border-top: 0.3mm solid var(--color-border); font-size: 8.6pt; color: var(--color-muted); }
@endsection

@section('content')
<header class="report__head">
  <div class="report__title">
    <h1>{{ __('documents.feedback_report.title') }}</h1>
    <p>{{ __('documents.feedback_report.subtitle') }}</p>
  </div>
  <img class="report__logo" src="{{ $assets->logo() }}" alt="{{ __('documents.logo_alt') }}">
</header>

<dl class="report__facts">
  <div><dt>{{ __('documents.feedback_report.entity') }}</dt><dd dir="auto">{{ $text($entity['name'] ?? null) }}</dd></div>
  <div><dt>{{ __('documents.feedback_report.entity_reference') }}</dt><dd class="ltr latin">{{ $entity['reference'] ?? '' }}</dd></div>
  <div><dt>{{ __('documents.feedback_report.entity_type') }}</dt><dd>{{ $text($entity['type_label'] ?? null) }}</dd></div>
  <div><dt>{{ __('documents.feedback_report.location') }}</dt><dd>{{ $text($entity['region'] ?? null) }} — {{ $text($entity['city'] ?? null) }}</dd></div>
  <div><dt>{{ __('documents.feedback_report.request') }}</dt><dd class="ltr latin">{{ $data['number'] ?? '' }}</dd></div>
  <div><dt>{{ __('documents.feedback_report.path') }}</dt><dd>{{ $text($data['path_label'] ?? null) }}</dd></div>
  <div><dt>{{ __('documents.feedback_report.completed_on') }}</dt><dd>@include('documents.partials.dates', ['gregorian' => $data['completed_on'] ?? '', 'hijri' => $data['completed_on_hijri'][$locale] ?? '', 'locale' => $locale])</dd></div>
  <div><dt>{{ __('documents.feedback_report.certificate') }}</dt><dd>@if ($certificate !== null)<bdi class="latin">{{ $certificate['number'] ?? '' }}</bdi>@else{{ __('documents.feedback_report.no_certificate') }}@endif</dd></div>
</dl>

<section class="report__summary">
  <bdi class="report__summary-percent" dir="ltr">{{ $data['percent'] ?? '' }}%</bdi>
  <div class="report__summary-label">{{ __('documents.feedback_report.overall') }} — <bdi class="latin" dir="ltr">{{ $data['total'] ?? '' }} / {{ $data['max_total'] ?? '' }}</bdi></div>
  @if (($certificate['level_label'] ?? null) !== null)
    <span class="report__summary-level">{{ $text($certificate['level_label']) }}</span>
  @endif
</section>

@if (($data['assessor_summary']['body'] ?? null) !== null)
  <h2 class="report__section">{{ __('documents.feedback_report.assessor_summary') }}</h2>
  <p class="report__assessor-summary" dir="auto">{{ $data['assessor_summary']['body'] }}</p>
@endif

<h2 class="report__section">{{ __('documents.feedback_report.results') }}</h2>
<table class="report__table">
  <thead>
    <tr>
      <th>{{ __('documents.feedback_report.code') }}</th>
      <th>{{ __('documents.feedback_report.criterion') }}</th>
      <th class="num">{{ __('documents.feedback_report.weight') }}</th>
      <th class="num">{{ __('documents.feedback_report.earned') }}</th>
      <th class="num">{{ __('documents.feedback_report.percent') }}</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($data['criteria'] ?? [] as $criterion)
      <tr>
        <td class="num">{{ $criterion['code'] ?? '' }}</td>
        <td>{{ $text($criterion['name'] ?? null) }}</td>
        <td class="num">{{ $criterion['weight'] ?? '' }}</td>
        <td class="num">{{ $criterion['earned'] ?? '' }}</td>
        <td class="num"><bdi dir="ltr">{{ $criterion['percent'] ?? '' }}%</bdi></td>
      </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td></td>
      <td>{{ __('documents.feedback_report.total') }}</td>
      <td class="num">{{ $data['max_total'] ?? '' }}</td>
      <td class="num">{{ $data['total'] ?? '' }}</td>
      <td class="num"><bdi dir="ltr">{{ $data['percent'] ?? '' }}%</bdi></td>
    </tr>
  </tfoot>
</table>

<h2 class="report__section">{{ __('documents.feedback_report.details') }}</h2>
@foreach ($data['criteria'] ?? [] as $criterion)
  <h3 class="report__criterion"><bdi>{{ $criterion['code'] ?? '' }}</bdi> — {{ $text($criterion['name'] ?? null) }} — <bdi dir="ltr">{{ $criterion['percent'] ?? '' }}%</bdi></h3>
  @foreach ($criterion['subs'] ?? [] as $sub)
    <article class="report__sub">
      <div class="report__sub-head">
        <span><bdi class="latin">{{ $sub['code'] ?? '' }}</bdi> {{ $text($sub['name'] ?? null) }}</span>
        <bdi class="num" dir="ltr">{{ $sub['percent'] ?? '' }}%</bdi>
      </div>
      @php($hasFeedback = ($sub['strengths'] ?? null) !== null || ($sub['improvement_points'] ?? null) !== null || ($sub['notes'] ?? null) !== null)
      @if (($sub['strengths'] ?? null) !== null)
        <div class="report__field report__field--strengths"><h3>{{ __('documents.feedback_report.strengths') }}</h3><p dir="auto">{{ $sub['strengths'] }}</p></div>
      @endif
      @if (($sub['improvement_points'] ?? null) !== null)
        <div class="report__field report__field--improvement"><h3>{{ __('documents.feedback_report.improvement_points') }}</h3><p dir="auto">{{ $sub['improvement_points'] }}</p></div>
      @endif
      @if (($sub['notes'] ?? null) !== null)
        <div class="report__field"><h3>{{ __('documents.feedback_report.notes') }}</h3><p dir="auto">{{ $sub['notes'] }}</p></div>
      @endif
      @unless ($hasFeedback)
        <p class="report__empty">{{ __('documents.feedback_report.none') }}</p>
      @endunless
    </article>
  @endforeach
@endforeach

<footer class="report__footer">
  {{ __('documents.feedback_report.footer') }}
  {{ __('documents.feedback_report.generated_on') }}: @include('documents.partials.dates', ['gregorian' => $data['generated_on'] ?? '', 'hijri' => $data['generated_on_hijri'][$locale] ?? '', 'locale' => $locale])
</footer>
@endsection
