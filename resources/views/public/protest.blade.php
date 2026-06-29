@extends('public.layout')

@section('title', 'プロテスト｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'プロテスト')

@push('styles')
<style>
  .jpba-protest-lead {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 16px;
  }

  .jpba-protest-flow {
    display: grid;
    gap: 10px;
    counter-reset: flow;
  }

  .jpba-protest-step {
    border: 1px solid var(--jpba-line);
    border-radius: 6px;
    padding: 12px;
    background: #fff;
  }

  .jpba-protest-step h3 {
    margin: 0 0 6px;
    color: var(--jpba-blue);
    font-size: 1rem;
    font-weight: 700;
  }

  .jpba-protest-table {
    min-width: 760px;
  }

  @media (max-width: 900px) {
    .jpba-protest-lead { grid-template-columns: 1fr; }
  }
</style>
@endpush

@section('content')
@php
  $intro = $protestConfig['intro'] ?? [];
  $flow = $protestConfig['flow'] ?? [];
  $links = $protestConfig['links'] ?? [];

  $formatDate = function ($value) {
      if (empty($value)) {
          return '-';
      }

      return \Carbon\Carbon::parse($value)->format('Y/n/j');
  };

  $formatPeriod = function ($start, $end) use ($formatDate) {
      $s = $formatDate($start);
      $e = $formatDate($end);

      if ($s !== '-' && $e !== '-' && $s !== $e) {
          return "{$s}-{$e}";
      }

      return $s !== '-' ? $s : $e;
  };
@endphp

<h1 class="jpba-page-title">プロテスト</h1>

<section class="jpba-panel" aria-labelledby="protest-overview-heading">
  <div class="jpba-protest-lead">
    <div>
      <h2 id="protest-overview-heading" class="jpba-section-title">プロボウラーになるには</h2>
      @foreach($intro as $paragraph)
        <p>{{ $paragraph }}</p>
      @endforeach
    </div>

    @if(!empty($links))
      <div>
        <h2 class="jpba-section-title">関連リンク</h2>
        <div class="jpba-link-grid">
          @foreach($links as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
          @endforeach
        </div>
      </div>
    @endif
  </div>
</section>

<section class="jpba-panel" aria-labelledby="protest-flow-heading">
  <h2 id="protest-flow-heading" class="jpba-section-title">プロテスト受験の流れ</h2>

  <div class="jpba-protest-flow">
    @foreach($flow as $step)
      <article class="jpba-protest-step">
        <h3>{{ $step['title'] }}</h3>
        <p class="mb-0">{{ $step['body'] }}</p>
      </article>
    @endforeach
  </div>
</section>

<section class="jpba-panel" aria-labelledby="protest-schedule-heading">
  <h2 id="protest-schedule-heading" class="jpba-section-title">実施日程・申請期間</h2>

  @if($proTestSchedules->count())
    <div class="table-responsive">
      <table class="jpba-data-table jpba-protest-table">
        <thead>
          <tr>
            <th>年度</th>
            <th>名称</th>
            <th>実施期間</th>
            <th>申請期間</th>
          </tr>
        </thead>
        <tbody>
          @foreach($proTestSchedules as $schedule)
            <tr>
              <td>{{ $schedule->year ?: '-' }}</td>
              <td>{{ $schedule->schedule_name }}</td>
              <td>{{ $formatPeriod($schedule->start_date, $schedule->end_date) }}</td>
              <td>{{ $formatPeriod($schedule->application_start, $schedule->application_end) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <p class="mb-0 text-muted">プロテスト日程はまだ登録されていません。現行の実施概要PDFまたはINFORMATIONを確認してください。</p>
  @endif
</section>

<section class="jpba-panel" aria-labelledby="protest-info-heading">
  <h2 id="protest-info-heading" class="jpba-section-title">プロテスト関連INFORMATION</h2>

  @if($proTestInformations->count())
    <div class="jpba-link-grid">
      @foreach($proTestInformations as $info)
        <a href="{{ route('informations.show', $info) }}">
          {{ optional($info->published_at ?: $info->updated_at)->format('Y/n/j') }}　{{ $info->title }}
          @if($info->files_count)
            <span class="text-muted">（添付あり）</span>
          @endif
        </a>
      @endforeach
    </div>
  @else
    <p class="mb-0 text-muted">公開中のプロテスト関連INFORMATIONはまだ登録されていません。</p>
  @endif
</section>
@endsection
