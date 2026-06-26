@extends('public.layout')

@section('title', 'JPBAについて｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'JPBAについて')

@section('content')
@php
  $overview = $association['overview'] ?? [];
  $description = $association['description'] ?? [];
  $businesses = $association['businesses'] ?? [];
  $documents = $association['documents'] ?? [];
@endphp

<h1 class="jpba-page-title">JPBAについて</h1>

<section class="jpba-panel" aria-labelledby="overview-heading">
  <h2 id="overview-heading" class="jpba-section-title">協会概要</h2>

  <table class="jpba-data-table">
    <tbody>
      @foreach($overview as $row)
        <tr>
          <th>{{ $row['label'] }}</th>
          <td>{{ $row['value'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</section>

<section class="jpba-panel" aria-labelledby="purpose-heading">
  <h2 id="purpose-heading" class="jpba-section-title">団体紹介</h2>

  @foreach($description as $paragraph)
    <p>{{ $paragraph }}</p>
  @endforeach

  @if(!empty($businesses))
    <h3 class="h6 fw-bold mt-3">事業</h3>
    <ol class="mb-0">
      @foreach($businesses as $business)
        <li>{{ $business }}</li>
      @endforeach
    </ol>
  @endif
</section>

<section class="jpba-panel" aria-labelledby="document-heading">
  <h2 id="document-heading" class="jpba-section-title">関連資料</h2>

  @if(!empty($documents))
    <div class="jpba-link-grid">
      @foreach($documents as $link)
        <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
      @endforeach
    </div>
  @else
    <p class="mb-0 text-muted">公開資料はまだ登録されていません。</p>
  @endif
</section>
@endsection
