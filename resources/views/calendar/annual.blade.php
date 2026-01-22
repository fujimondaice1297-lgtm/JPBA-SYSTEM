@extends('layouts.app')

@section('content')
<style>
  /* 年間はセル塗りはしない。イベント名の頭に小さな色チップだけ表示 */
  .dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; transform: translateY(-1px); }
  .dot-men { background:#6fb7ff; }     /* 男子 */
  .dot-women { background:#ff8eb0; }   /* 女子 */
  .dot-mixed { background:#6edc74; }   /* 混合/未設定 */
  .dot-manual { background:#a78bfa; }  /* 手入力（プロテスト/承認/その他） */
</style>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>{{ $year }}年 年間スケジュール</h2>
    <div class="d-flex gap-2">
      @php
        $prevY = $year - 1; $nextY = $year + 1;
        $years = range($year-3, $year+3);
      @endphp
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.annual', [$prevY]) }}">← {{ $prevY }}</a>

      <form method="GET" action="{{ route('calendar.annual') }}" class="d-flex gap-2">
        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
          @foreach($years as $y)
            <option value="{{ $y }}" {{ $y==$year ? 'selected' : '' }}>{{ $y }}年</option>
          @endforeach
        </select>
      </form>

      <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.annual', [$nextY]) }}">{{ $nextY }} →</a>

      <a class="btn btn-outline-secondary btn-sm" href="{{ route('athlete.index') }}">インデックスに戻る</a>
      <a class="btn btn-outline-danger btn-sm" href="{{ route('calendar.annual.pdf', [$year]) }}" target="_blank">PDF</a>
      <a class="btn btn-outline-primary btn-sm" href="{{ route('calendar_events.create') }}">予定を手入力</a>
    </div>
  </div>

  @for($m=1;$m<=12;$m++)
    <div class="d-flex justify-content-between align-items-center mt-4">
      <h4 class="mb-0">{{ $m }}月</h4>
      <a class="btn btn-primary btn-sm" href="{{ route('calendar.monthly', [$year, $m]) }}">この月のカレンダーへ</a>
    </div>

    <table class="table table-sm table-striped align-middle mt-2">
      <thead><tr>
        <th style="width:220px;">日程</th>
        <th>大会名 / イベント名</th>
        <th style="width:100px;">種別</th>
        <th style="width:240px;">会場</th>
      </tr></thead>
      <tbody>
      @forelse(($grouped[$m] ?? collect()) as $t)
        @php
          // 手入力（CalendarEvent）かどうか
          $isManual = $t instanceof \App\Models\CalendarEvent;

          // 表示テキスト
          $name  = $isManual ? $t->title : ($t->name ?? '-');
          $venue = $isManual ? ($t->venue ?? null) : ($t->venue_name ?? null);

          // 期間表示
          $sd = $t->start_date ? \Carbon\Carbon::parse($t->start_date) : null;
          $ed = $t->end_date   ? \Carbon\Carbon::parse($t->end_date)   : $sd;
          $period = $sd
            ? ($sd->equalTo($ed) ? $sd->format('Y/m/d') : $sd->format('Y/m/d').' - '.$ed->format('Y/m/d'))
            : '—';

          // 種別表示
          $kindText = $isManual
            ? ($t->kind_label ?? 'その他')  /* ← モデルのアクセサを利用 */
            : '—';

          // 色チップ
          $dotClass = $isManual ? 'dot-manual'
                     : ((property_exists($t,'gender') && $t->gender === 'M') ? 'dot-men'
                     : ((property_exists($t,'gender') && $t->gender === 'F') ? 'dot-women' : 'dot-mixed'));
        @endphp
        <tr>
          <td>{{ $period }}</td>
          <td><span class="dot {{ $dotClass }}"></span>{{ $name }}</td>
          <td>{{ $kindText }}</td>
          <td>{{ $venue ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-muted">該当なし</td></tr>
      @endforelse
      </tbody>
    </table>
  @endfor
</div>
@endsection
