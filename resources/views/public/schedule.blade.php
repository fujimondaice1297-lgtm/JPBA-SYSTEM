@extends('public.layout')

@section('title', 'スケジュール｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'スケジュール')

@push('styles')
<style>
  .jpba-year-nav { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
  .jpba-year-nav a { display:inline-flex; align-items:center; min-height:34px; padding:4px 12px; border:1px solid var(--jpba-line); border-radius:5px; background:var(--jpba-soft); text-decoration:none; font-weight:700; }
  .jpba-year-nav a.active { background:var(--jpba-blue); color:#fff; border-color:var(--jpba-blue); }
  .annual-schedule-heading { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-bottom:12px; }
  .annual-schedule-heading h2 { margin:0; color:var(--jpba-blue); font-size:1.22rem; font-weight:800; }
  .annual-schedule-meta { color:#667085; font-size:.8rem; text-align:right; }
  .annual-schedule-scroll { overflow-x:auto; border:1px solid #9ca8b8; }
  .annual-schedule-table { width:100%; min-width:960px; border-collapse:collapse; background:#fff; font-size:.77rem; line-height:1.4; }
  .annual-schedule-table th, .annual-schedule-table td { border:1px solid #aeb8c5; padding:6px 7px; vertical-align:middle; white-space:pre-line; }
  .annual-schedule-table thead th { background:#eaf1f8; color:#20334a; text-align:center; font-weight:800; }
  .annual-schedule-table .month { width:48px; background:#eef4fa; color:var(--jpba-blue); text-align:center; font-size:.9rem; font-weight:800; }
  .annual-schedule-table .date { width:126px; text-align:center; }
  .annual-schedule-table .title { min-width:270px; font-weight:700; }
  .annual-schedule-table .eligibility { width:90px; text-align:center; }
  .annual-schedule-table .region { width:58px; text-align:center; }
  .annual-schedule-table .venue { min-width:190px; }
  .annual-schedule-table .mark { width:40px; text-align:center; font-weight:800; }
  .annual-schedule-table .note { min-width:150px; }
  .annual-schedule-table tr.qualifier td:not(.month) { background:#edf6ff; }
  .annual-schedule-notice { display:flex; justify-content:space-between; gap:16px; margin-top:10px; color:#667085; font-size:.78rem; }
  .annual-schedule-fallback { margin-top:16px; }
  .annual-schedule-download { display:inline-flex; align-items:center; padding:6px 11px; border-radius:5px; background:#c5282f; color:#fff; font-weight:700; text-decoration:none; }
  .annual-schedule-download:hover { color:#fff; background:#a51e25; }
  @media (max-width:720px) { .annual-schedule-heading, .annual-schedule-notice { align-items:flex-start; flex-direction:column; } .annual-schedule-meta { text-align:left; } }
</style>
@endpush

@section('content')
<h1 class="jpba-page-title">スケジュール</h1>

@if(!empty($availableYears))
  <nav class="jpba-year-nav" aria-label="年度切替">
    @foreach($availableYears as $availableYear)
      <a class="{{ (int)$availableYear === (int)$year ? 'active' : '' }}" href="{{ route('public.schedule', ['year' => $availableYear]) }}">{{ $availableYear }}年</a>
    @endforeach
  </nav>
@endif

@if(isset($annualSchedule))
  <div class="annual-schedule-heading">
    <h2>{{ $annualSchedule->year }}年 {{ $annualSchedule->title }}</h2>
    <div class="annual-schedule-meta">
      @if($annualSchedule->source_updated_on){{ $annualSchedule->source_updated_on->format('Y.n.j') }}現在@endif
      <a class="annual-schedule-download ms-2" href="{{ route('annual_schedules.pdf', $annualSchedule->year) }}" target="_blank" rel="noopener">PDF</a>
    </div>
  </div>

  <div class="annual-schedule-scroll">
    <table class="annual-schedule-table">
      <thead>
        <tr>
          <th rowspan="2">月</th><th rowspan="2">日（曜日）</th><th rowspan="2">トーナメント名</th><th rowspan="2">出場資格</th><th colspan="2">会場</th><th colspan="3">ランキング算入</th><th rowspan="2">公式<br>タイトル</th><th rowspan="2">備考</th>
        </tr>
        <tr><th>地区</th><th>ボウリング場</th><th>ポイント</th><th>AVG</th><th>賞金</th></tr>
      </thead>
      <tbody>
      @for($month = 1; $month <= 12; $month++)
        @php $monthRows = $groupedAnnualRows->get($month, collect()); @endphp
        @if($monthRows->isEmpty())
          <tr><th class="month">{{ $month }}月</th><td colspan="10" class="text-muted">予定は未登録です。</td></tr>
        @else
          @foreach($monthRows as $row)
            <tr class="{{ $row->row_type }}">
              @if($loop->first)<th class="month" rowspan="{{ $monthRows->count() }}">{{ $month }}月</th>@endif
              <td class="date">{{ $row->date_label }}</td>
              <td class="title">{{ $row->title }}</td>
              <td class="eligibility">{{ $row->eligibility }}</td>
              <td class="region">{{ $row->region }}</td>
              <td class="venue">{{ $row->venue }}</td>
              <td class="mark">{{ $row->point_mark }}</td>
              <td class="mark">{{ $row->average_mark }}</td>
              <td class="mark">{{ $row->prize_mark }}</td>
              <td class="mark">{{ $row->title_mark }}</td>
              <td class="note">{{ $row->note }}</td>
            </tr>
          @endforeach
        @endif
      @endfor
      </tbody>
    </table>
  </div>
  <div class="annual-schedule-notice">
    <span>{{ $annualSchedule->notice }}</span>
    <span>予定は変更になる場合があります。</span>
  </div>
@elseif($scheduleRows->count())
  <div class="annual-schedule-fallback jpba-panel">
    <p class="mb-2">{{ $year }}年は従来データから表示しています。</p>
    @foreach($groupedScheduleRows as $month => $rows)
      <h2 class="jpba-section-title">{{ $month }}月</h2>
      <ul>
      @foreach($rows as $row)<li class="mb-2"><strong>{{ $row['period'] }}</strong>　{{ $row['title'] }} @if($row['venue'])（{{ $row['venue'] }}）@endif</li>@endforeach
      </ul>
    @endforeach
  </div>
@else
  <div class="jpba-panel text-muted">表示できるスケジュールはまだ登録されていません。</div>
@endif
@endsection
