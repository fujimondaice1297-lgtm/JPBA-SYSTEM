@extends('public.layout')

@section('title', ($pageConfig['title'] ?? 'JPBA') . '｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', $pageConfig['breadcrumb'] ?? ($pageConfig['title'] ?? 'JPBA'))

@push('styles')
<style>
  .jpba-static-lead {
    display: grid;
    gap: 8px;
  }

  .jpba-static-section {
    margin-top: 16px;
  }

  .jpba-static-section h3 {
    margin: 0 0 8px;
    color: var(--jpba-blue);
    font-size: 1rem;
    font-weight: 700;
  }

  .jpba-static-list {
    margin-bottom: 0;
  }
</style>
@endpush

@section('content')
@php
  $summary = $pageConfig['summary'] ?? [];
  $sections = $pageConfig['sections'] ?? [];
  $table = $pageConfig['table'] ?? [];
  $links = $pageConfig['links'] ?? [];
@endphp

<h1 class="jpba-page-title">{{ $pageConfig['title'] ?? 'JPBA' }}</h1>

<section class="jpba-panel" aria-labelledby="static-overview-heading">
  <h2 id="static-overview-heading" class="jpba-section-title">{{ $pageConfig['title'] ?? '概要' }}</h2>

  @if(!empty($summary))
    <div class="jpba-static-lead">
      @foreach($summary as $paragraph)
        <p class="mb-0">{{ $paragraph }}</p>
      @endforeach
    </div>
  @endif

  @if(!empty($table))
    <div class="table-responsive mt-3">
      <table class="jpba-data-table">
        <tbody>
          @foreach($table as $row)
            <tr>
              <th>{{ $row['label'] }}</th>
              <td>{{ $row['value'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @foreach($sections as $section)
    <div class="jpba-static-section">
      <h3>{{ $section['heading'] }}</h3>
      @if(!empty($section['items']))
        <ul class="jpba-static-list">
          @foreach($section['items'] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      @endif
    </div>
  @endforeach
</section>

@if(!empty($links))
  <section class="jpba-panel" aria-labelledby="static-links-heading">
    <h2 id="static-links-heading" class="jpba-section-title">関連リンク</h2>
    <div class="jpba-link-grid">
      @foreach($links as $link)
        <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
      @endforeach
    </div>
  </section>
@endif
@endsection
