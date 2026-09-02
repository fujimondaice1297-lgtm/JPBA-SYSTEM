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
  .annual-schedule-guide { margin:0 0 14px; padding:10px 12px; border-left:4px solid var(--jpba-blue); background:#f4f7fb; color:#53606f; font-size:.82rem; }
  .annual-schedule-month-nav { display:grid; grid-template-columns:repeat(12, minmax(0, 1fr)); gap:5px; margin-bottom:18px; }
  .annual-schedule-month-nav a { display:flex; justify-content:center; align-items:center; min-height:34px; border:1px solid #bdc9d8; border-radius:5px; background:#fff; color:var(--jpba-blue); text-decoration:none; font-weight:800; }
  .annual-schedule-month-nav a:hover, .annual-schedule-month-nav a:focus { border-color:var(--jpba-blue); background:#edf4fc; }
  .annual-schedule-month-nav a.current { border-color:#c5282f; box-shadow:inset 0 -3px #c5282f; }
  .annual-schedule-month { margin-bottom:20px; border:1px solid #bdc9d8; border-radius:7px; background:#fff; overflow:hidden; scroll-margin-top:12px; }
  .annual-schedule-month-heading { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0; padding:9px 14px; background:var(--jpba-blue); color:#fff; font-size:1.05rem; font-weight:800; }
  .annual-schedule-month-count { font-size:.75rem; font-weight:500; opacity:.9; }
  .annual-schedule-event { display:grid; grid-template-columns:minmax(150px, 180px) minmax(0, 1fr); border-top:1px solid #d8e0ea; }
  .annual-schedule-event:first-child { border-top:0; }
  .annual-schedule-event.qualifier { background:#f2f8ff; }
  .annual-schedule-event.note { background:#fffaf0; }
  .annual-schedule-event.placeholder { background:#f8fafc; }
  .annual-schedule-date { display:flex; flex-direction:column; justify-content:center; gap:3px; padding:13px 14px; border-right:1px solid #d8e0ea; background:rgba(234,241,248,.72); color:#26384d; font-weight:800; white-space:pre-line; }
  .annual-schedule-date-label { color:#6b7788; font-size:.7rem; font-weight:700; }
  .annual-schedule-body { min-width:0; padding:12px 15px 13px; }
  .annual-schedule-event-title { margin:0; color:#173d70; font-size:.96rem; font-weight:800; line-height:1.55; overflow-wrap:anywhere; white-space:pre-line; }
  .annual-schedule-event-title a { text-decoration-thickness:1px; text-underline-offset:3px; }
  .annual-schedule-tags { display:flex; flex-wrap:wrap; gap:6px; margin-top:7px; }
  .annual-schedule-tag { display:inline-flex; align-items:center; min-height:24px; padding:2px 8px; border:1px solid #c9d4e1; border-radius:999px; background:#f7f9fc; color:#45566a; font-size:.74rem; line-height:1.35; white-space:pre-line; }
  .annual-schedule-location { display:grid; grid-template-columns:58px minmax(0, 1fr); gap:8px; margin-top:8px; color:#34465a; }
  .annual-schedule-field-label { color:#697789; font-size:.74rem; font-weight:700; }
  .annual-schedule-location-value { overflow-wrap:anywhere; white-space:pre-line; }
  .annual-schedule-ranking { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:9px; }
  .annual-schedule-ranking-title { margin-right:2px; color:#697789; font-size:.74rem; font-weight:700; }
  .annual-schedule-mark { display:inline-flex; align-items:center; gap:5px; min-height:25px; padding:2px 8px; border:1px solid #b9c9db; border-radius:4px; background:#edf4fc; color:#214d7d; font-size:.73rem; }
  .annual-schedule-mark strong { font-size:.8rem; }
  .annual-schedule-official { border-color:#e2b3b6; background:#fff3f3; color:#8d2227; }
  .annual-schedule-note { margin-top:9px; padding:7px 9px; border-left:3px solid #d7a12f; background:#fffaf0; color:#5d4a20; font-size:.78rem; overflow-wrap:anywhere; white-space:pre-line; }
  .annual-schedule-empty { padding:14px; color:#697789; }
  .annual-schedule-notice { display:flex; justify-content:space-between; gap:16px; margin-top:10px; color:#667085; font-size:.78rem; }
  .annual-schedule-fallback { margin-top:16px; }
  .annual-schedule-download { display:inline-flex; align-items:center; padding:6px 11px; border-radius:5px; background:#c5282f; color:#fff; font-weight:700; text-decoration:none; }
  .annual-schedule-download:hover { color:#fff; background:#a51e25; }
  @media (max-width:900px) { .annual-schedule-month-nav { grid-template-columns:repeat(6, minmax(0, 1fr)); } }
  @media (max-width:720px) {
    .annual-schedule-heading, .annual-schedule-notice { align-items:flex-start; flex-direction:column; }
    .annual-schedule-meta { text-align:left; }
    .annual-schedule-month-nav { grid-template-columns:repeat(4, minmax(0, 1fr)); }
    .annual-schedule-event { grid-template-columns:1fr; }
    .annual-schedule-date { flex-direction:row; justify-content:flex-start; align-items:baseline; padding:8px 12px; border-right:0; border-bottom:1px solid #d8e0ea; }
    .annual-schedule-body { padding:11px 12px 13px; }
    .annual-schedule-location { grid-template-columns:48px minmax(0, 1fr); }
  }
  @media (max-width:430px) { .annual-schedule-month-nav { grid-template-columns:repeat(3, minmax(0, 1fr)); } }
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

  <p class="annual-schedule-guide">ブラウザでは月ごとに、日程・トーナメント名・出場資格・会場・ランキング算入・公式タイトル・備考をまとめて表示しています。</p>

  <nav class="annual-schedule-month-nav" aria-label="月別スケジュールへ移動">
    @for($month = 1; $month <= 12; $month++)
      <a href="#schedule-month-{{ $month }}" class="{{ (int)$annualSchedule->year === (int)now()->year && $month === (int)now()->month ? 'current' : '' }}">{{ $month }}月</a>
    @endfor
  </nav>

  <div class="annual-schedule-browser">
    @for($month = 1; $month <= 12; $month++)
      @php
        $monthRows = $groupedAnnualRows->get($month, collect());
        $eventCount = $monthRows->filter(fn ($row) => filled($row->title))->count();
      @endphp
      <section class="annual-schedule-month" id="schedule-month-{{ $month }}" aria-labelledby="schedule-month-heading-{{ $month }}">
        <h3 class="annual-schedule-month-heading" id="schedule-month-heading-{{ $month }}">
          <span>{{ $month }}月</span>
          @if($eventCount > 0)<span class="annual-schedule-month-count">トーナメント等 {{ $eventCount }}件</span>@endif
        </h3>
        <div class="annual-schedule-month-events">
          @if($monthRows->isEmpty())
            <div class="annual-schedule-empty">予定は未登録です。</div>
          @else
            @foreach($monthRows as $row)
              @php
                $hasTitle = filled($row->title);
                $isNoteOnly = !$hasTitle && filled($row->note);
                $hasRanking = filled($row->point_mark) || filled($row->average_mark) || filled($row->prize_mark) || filled($row->title_mark);
                $rowClass = $isNoteOnly ? 'note' : (!$hasTitle ? 'placeholder' : $row->row_type);
              @endphp
              <article class="annual-schedule-event {{ $rowClass }}">
                <div class="annual-schedule-date">
                  <span class="annual-schedule-date-label">日（曜日）</span>
                  <span>{{ filled($row->date_label) ? $row->date_label : '日程未定' }}</span>
                </div>
                <div class="annual-schedule-body">
                  @if($hasTitle)
                    <h4 class="annual-schedule-event-title">
                      @if($row->tournament)
                        <a href="{{ route('public.tournaments.show', $row->tournament) }}">{{ $row->title }}</a>
                      @else
                        {{ $row->title }}
                      @endif
                    </h4>
                    @if(filled($row->eligibility) || filled($row->region))
                      <div class="annual-schedule-tags">
                        @if(filled($row->eligibility))<span class="annual-schedule-tag">出場資格：{{ $row->eligibility }}</span>@endif
                        @if(filled($row->region))<span class="annual-schedule-tag">地区：{{ $row->region }}</span>@endif
                      </div>
                    @endif
                    @if(filled($row->venue))
                      <div class="annual-schedule-location">
                        <span class="annual-schedule-field-label">会場</span>
                        <span class="annual-schedule-location-value">{{ $row->venue }}</span>
                      </div>
                    @endif
                    @if($hasRanking)
                      <div class="annual-schedule-ranking" aria-label="ランキング算入・公式タイトル">
                        <span class="annual-schedule-ranking-title">ランキング算入</span>
                        @if(filled($row->point_mark))<span class="annual-schedule-mark">ポイント <strong>{{ $row->point_mark }}</strong></span>@endif
                        @if(filled($row->average_mark))<span class="annual-schedule-mark">AVG <strong>{{ $row->average_mark }}</strong></span>@endif
                        @if(filled($row->prize_mark))<span class="annual-schedule-mark">賞金 <strong>{{ $row->prize_mark }}</strong></span>@endif
                        @if(filled($row->title_mark))<span class="annual-schedule-mark annual-schedule-official">公式タイトル <strong>{{ $row->title_mark }}</strong></span>@endif
                      </div>
                    @endif
                  @elseif($isNoteOnly)
                    <h4 class="annual-schedule-event-title">お知らせ</h4>
                  @else
                    <h4 class="annual-schedule-event-title">日程調整中</h4>
                  @endif
                  @if(filled($row->note))<div class="annual-schedule-note"><span class="annual-schedule-field-label">備考</span><br>{{ $row->note }}</div>@endif
                </div>
              </article>
            @endforeach
          @endif
        </div>
      </section>
    @endfor
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
