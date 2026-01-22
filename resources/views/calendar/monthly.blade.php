@extends('layouts.app')

@section('content')
  <style>
    /* セル背景（薄め） */
    .bg-men    { background: #e7f4ff !important; }   /* 水色 */
    .bg-women  { background: #ffe8f0 !important; }   /* ピンク */
    .bg-mixed  { background: #e9f8ea !important; }   /* 薄緑 */
    .bg-manual { background: #f0e9ff !important; }   /* 薄紫 */

    /* 祝日（数字を赤、背景うっすら） */
    .text-holiday { color:#d93025 !important; }
    .bg-holiday   { background:#fff4f4 !important; }

    /* 土日色（Bootstrapより強く）*/
    .text-sat { color:#1e90ff !important; }
    .text-sun { color:#d93025 !important; }
    .calendar thead th.text-sat { color:#1e90ff !important; }
    .calendar thead th.text-sun { color:#d93025 !important; }

    /* セルの枠 */
    .cell-border { outline: 2px solid rgba(0,0,0,.06); outline-offset:-2px; }

    /* イベント行の色チップ */
    .dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; transform: translateY(-1px); }
    .dot-men { background:#6fb7ff; }
    .dot-women { background:#ff8eb0; }
    .dot-mixed { background:#6edc74; }
    .dot-manual { background:#a78bfa; }

    /* 今日を目立たせる */
    .today-ring { box-shadow: 0 0 0 2px #0d6efd inset; border-radius: 8px; }

    /* ドラッグ選択の見た目 */
    .range-mark { background: rgba(13,110,253,.12) !important; }

    /* 凡例（固定） */
    .legend { position: sticky; top: 0; z-index: 5; background: #fff; padding: .5rem .75rem; border: 1px solid #eee; border-radius: .5rem; }
    .legend .item { display:inline-flex; align-items:center; gap:.4rem; margin-right:.8rem; font-size:.9rem; }
    .legend .sw { width:12px; height:12px; border-radius:3px; display:inline-block; }
    .sw-men { background:#e7f4ff; border:1px solid #6fb7ff55; }
    .sw-women { background:#ffe8f0; border:1px solid #ff8eb055; }
    .sw-mixed { background:#e9f8ea; border:1px solid #6edc7455; }
    .sw-manual { background:#f0e9ff; border:1px solid #a78bfa55; }
    .sw-holiday { background:#fff4f4; border:1px solid #d9302555; }

    /* モバイル（縦積み） */
    @media (max-width: 576px) {
      .calendar thead { display: none; }
      .calendar tr { display: block; margin-bottom: .75rem; }
      .calendar td { display: block; width: 100% !important; height: auto !important; }
      .day-head { display:flex; align-items:center; gap:.5rem; }
      .day-dow { font-size:.8rem; color:#6c757d; }
    }
  </style>

  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2>{{ $year }}年{{ $month }}月 カレンダー</h2>
      <div class="d-flex gap-2">
        @php
          $prev = \Carbon\Carbon::create($year,$month,1)->subMonth();
          $next = \Carbon\Carbon::create($year,$month,1)->addMonth();
        @endphp
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.annual', [$year]) }}">年間へ</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('athlete.index') }}">インデックスへ戻る</a>
        <a class="btn btn-outline-danger btn-sm" href="{{ route('calendar.monthly.pdf', [$year,$month]) }}" target="_blank">PDF</a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('calendar_events.create') }}">予定を手入力</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.monthly', [$prev->year,$prev->month]) }}">← 前月</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.monthly', [$next->year,$next->month]) }}">翌月 →</a>
      </div>
    </div>

    {{-- 凡例（固定） --}}
    <div class="legend mb-2">
      <span class="item"><span class="sw sw-men"></span>男子</span>
      <span class="item"><span class="sw sw-women"></span>女子</span>
      <span class="item"><span class="sw sw-mixed"></span>男女/未設定</span>
      <span class="item"><span class="sw sw-manual"></span>手入力/承認/その他</span>
      <span class="item"><span class="sw sw-holiday"></span>祝日</span>
    </div>

    <table class="table table-bordered calendar">
      <thead>
        <tr class="text-center bg-light">
          <th style="width:54px;">Wk</th>
          @php
            $wd = [
              ['月',''],['火',''],['水',''],['木',''],['金',''],
              ['土','text-sat'],['日','text-sun'],
            ];
          @endphp
          @foreach($wd as [$label,$cls])
            <th class="{{ $cls }}">{{ $label }}</th>
          @endforeach
        </tr>
      </thead>

      <tbody id="cal-body">
        @php $d = $gridStart->copy(); @endphp
        @while($d <= $gridEnd)
          <tr style="height:140px;">
            @php $weekNo = $d->isoWeek(); @endphp
            <td class="text-center bg-light fw-semibold align-middle d-none d-sm-table-cell" style="width:54px;">{{ $weekNo }}</td>

            @for($i=0; $i<7; $i++)
              @php
                $key = $d->toDateString();
                $isOtherMonth = ($d->month != $month);
                $eventsToday = $map[$key] ?? [];
                $baseBg = $bgMap[$key] ?? '';
                $w = $d->dayOfWeekIso; // 1..7
                $meta = $dayMeta[$key] ?? null;
                $isHoliday = $meta && ($meta['is_holiday'] ?? false);
                $dayColor = $isHoliday ? 'text-holiday' : ($w===7 ? 'text-sun' : ($w===6 ? 'text-sat' : ''));
                $bgClass = $isOtherMonth ? 'bg-body-tertiary' : trim($baseBg.' '.($isHoliday ? 'bg-holiday' : ''));
                $isToday = $key === now()->toDateString();
                $dowLabel = ['月','火','水','木','金','土','日'][$w-1];
              @endphp

              <td class="align-top {{ $bgClass }} cell-border {{ $isToday ? 'today-ring' : '' }}"
                  style="width:14.28%;"
                  data-date="{{ $key }}">
                <div class="day-head small fw-bold {{ $isOtherMonth ? 'text-muted' : $dayColor }}">
                  <span class="d-sm-none badge text-bg-light">{{ $dowLabel }}</span>
                  <span>{{ $d->day }}</span>
                </div>

                @if($meta && ($meta['holiday_name'] ?? null))
                  <div class="small {{ $isOtherMonth ? 'text-muted' : 'text-holiday' }}">
                    {{ $meta['holiday_name'] }}
                  </div>
                @endif

                @foreach($eventsToday as $ev)
                  @php
                    $isManual = $ev instanceof \App\Models\CalendarEvent;
                    $isNonOfficial = (!$isManual) && in_array($ev->official_type ?? 'official', ['approved','other'], true);
                    $dot = ($isManual || $isNonOfficial) ? 'dot-manual'
                          : ((property_exists($ev,'gender') && $ev->gender === 'M') ? 'dot-men'
                          : ((property_exists($ev,'gender') && $ev->gender === 'F') ? 'dot-women' : 'dot-mixed'));

                    $name  = $isManual ? $ev->title : $ev->name;
                    $venue = $isManual ? $ev->venue : $ev->venue_name;

                    $sd = $ev->start_date ? \Carbon\Carbon::parse($ev->start_date) : null;
                    $ed = $ev->end_date ? \Carbon\Carbon::parse($ev->end_date) : $sd;
                    $period = $sd ? ($sd->equalTo($ed) ? $sd->format('Y/m/d') : $sd->format('Y/m/d').' - '.$ed->format('Y/m/d')) : '';
                    $detailUrl = $isManual ? null : route('tournaments.show', $ev->id);

                    // ★ 表示バッジ（手入力は kind を見て日本語化）
                    if ($isManual) {
                        $kind = $ev->kind ?? 'other';
                        $kindLabel = $kind === 'pro_test' ? 'プロテスト' : ($kind === 'approved' ? '承認大会' : 'その他');
                        $badge = '（' . $kindLabel . '）';
                    } else {
                        $official = $ev->official_type ?? 'official';
                        $badge = '（' . ($official === 'approved' ? '承認大会' : ($official === 'other' ? 'その他' : '公認')) . '）';
                    }
                  @endphp

                  <div class="small mt-1">
                    @if($detailUrl)
                      <a href="{{ $detailUrl }}" class="text-decoration-none">
                        <span class="dot {{ $dot }}"></span>{{ $name }}
                      </a>
                    @else
                      <span class="dot {{ $dot }}"></span>{{ $name }}
                    @endif
                    <span class="text-muted small">{{ $badge }}</span>
                    <div class="text-muted small">
                      {{ $period }} @if($venue)｜{{ $venue }}@endif
                    </div>
                  </div>
                @endforeach
              </td>

              @php $d->addDay(); @endphp
            @endfor
          </tr>
        @endwhile
      </tbody>
    </table>
  </div>

  {{-- ★ 新規作成モーダル（ドラッグ確定後に開く） --}}
  <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" method="POST" action="{{ route('calendar_events.store') }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">新規イベント（手入力）</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">種別</label>
            <select name="kind" class="form-select" required>
              <option value="pro_test">プロテスト</option>
              <option value="approved">承認大会</option>
              <option value="other" selected>その他</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">タイトル</label>
            <input name="title" class="form-control" placeholder="例）○○承認大会" required>
          </div>
          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">開始日</label>
              <input type="date" name="start_date" id="modal-start" class="form-control" required>
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">終了日</label>
              <input type="date" name="end_date" id="modal-end" class="form-control" required>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">会場</label>
            <input name="venue" class="form-control" placeholder="任意">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">登録</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
        </div>
      </form>
    </div>
  </div>

  {{-- ★ ドラッグ選択スクリプト（Bootstrap5前提・素のJS） --}}
  <script>
    (function(){
      const body = document.getElementById('cal-body');
      if(!body) return;

      let dragging = false, startCell = null, endCell = null;

      function cellDate(td){ return td?.getAttribute('data-date'); }
      function isCell(el){ return el && el.tagName === 'TD' && el.hasAttribute('data-date'); }

      function clearRange(){
        body.querySelectorAll('.range-mark').forEach(el=>el.classList.remove('range-mark'));
      }
      function markRange(a, b){
        clearRange();
        if(!a || !b) return;
        const d1 = cellDate(a), d2 = cellDate(b);
        if(!d1 || !d2) return;
        const [s,e] = d1 <= d2 ? [d1,d2] : [d2,d1];
        body.querySelectorAll('td[data-date]').forEach(td=>{
          const d = cellDate(td);
          if(d >= s && d <= e) td.classList.add('range-mark');
        });
      }

      body.addEventListener('mousedown', e=>{
        if(!isCell(e.target.closest('td'))) return;
        dragging = true;
        startCell = e.target.closest('td');
        endCell = startCell;
        markRange(startCell, endCell);
        e.preventDefault();
      });
      body.addEventListener('mouseover', e=>{
        if(!dragging) return;
        const td = e.target.closest('td');
        if(!isCell(td)) return;
        endCell = td;
        markRange(startCell, endCell);
      });
      window.addEventListener('mouseup', ()=>{
        if(!dragging) return;
        dragging = false;
        if(!startCell || !endCell) { clearRange(); return; }

        const d1 = cellDate(startCell), d2 = cellDate(endCell);
        if(!d1 || !d2) { clearRange(); return; }
        const start = d1 <= d2 ? d1 : d2;
        const end   = d1 <= d2 ? d2 : d1;

        // モーダルに日付を投入
        document.getElementById('modal-start').value = start;
        document.getElementById('modal-end').value   = end;

        // Bootstrap Modal 表示
        const m = new bootstrap.Modal(document.getElementById('eventModal'));
        m.show();

        // 余韻で選択残らないよう掃除
        setTimeout(clearRange, 200);
      });
    })();
  </script>
@endsection
