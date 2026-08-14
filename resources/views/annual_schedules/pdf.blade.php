<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 7mm 5mm 6mm; }
  body { margin:0; font-family:ipaexg, sans-serif; color:#111; font-size:5.15pt; line-height:1.06; }
  .header { position:relative; height:27px; margin-bottom:2px; }
  .logo { position:absolute; left:0; top:0; width:54px; }
  .title { text-align:center; }
  .title .year { font-size:12pt; letter-spacing:2px; }
  .title .name { font-size:9pt; margin-top:0; }
  .org { position:absolute; right:0; top:0; font-size:5pt; }
  .meta { display:flex; justify-content:space-between; margin-bottom:2px; font-size:4.5pt; }
  table { width:100%; border-collapse:collapse; table-layout:fixed; }
  th, td { border:.4pt solid #333; padding:.5px 1px; vertical-align:middle; white-space:pre-line; overflow-wrap:anywhere; font-weight:normal; }
  thead th { background:#edf1f4; text-align:center; }
  .month { width:4.2%; text-align:center; background:#f0f3f6; }
  .date { width:13.5%; text-align:center; }
  .event { width:28%; }
  .eligibility { width:8.5%; text-align:center; }
  .region { width:5.5%; text-align:center; }
  .venue { width:17%; }
  .mark { width:3.4%; text-align:center; }
  .note { width:12%; }
  tr.qualifier td { background:#edf6ff; }
</style>
</head>
<body>
  <div class="header">
    <img class="logo" src="{{ public_path('images/jpba_logo.png') }}" alt="JPBA">
    <div class="title"><div class="year">{{ $schedule->year }}年</div><div class="name">{{ $schedule->title }}</div></div>
    <div class="org">公益社団法人 日本プロボウリング協会</div>
  </div>
  <div class="meta"><span>{{ $schedule->notice }}</span><span>@if($schedule->source_updated_on){{ $schedule->source_updated_on->format('Y.n.j') }}現在@endif</span></div>
  <table>
    <thead>
      <tr><th rowspan="2" class="month">月</th><th rowspan="2" class="date">日（曜日）</th><th rowspan="2" class="event">トーナメント名</th><th rowspan="2" class="eligibility">出場資格</th><th colspan="2">会場</th><th colspan="3">ランキング算入</th><th rowspan="2" class="mark">公式<br>タイトル</th><th rowspan="2" class="note">備考</th></tr>
      <tr><th class="region">地区</th><th class="venue">ボウリング場</th><th class="mark">ポイント</th><th class="mark">AVG</th><th class="mark">賞金</th></tr>
    </thead>
    <tbody>
    @for($month = 1; $month <= 12; $month++)
      @php $monthRows = $groupedRows->get($month, collect()); @endphp
      @if($monthRows->isEmpty())
        <tr><th class="month">{{ $month }}月</th><td colspan="10"></td></tr>
      @else
        @foreach($monthRows as $row)
          <tr class="{{ $row->row_type }}">
            @if($loop->first)<th rowspan="{{ $monthRows->count() }}" class="month">{{ $month }}月</th>@endif
            <td class="date">{{ $row->date_label }}</td><td class="event">{{ $row->title }}</td><td class="eligibility">{{ $row->eligibility }}</td><td class="region">{{ $row->region }}</td><td class="venue">{{ $row->venue }}</td><td class="mark">{{ $row->point_mark }}</td><td class="mark">{{ $row->average_mark }}</td><td class="mark">{{ $row->prize_mark }}</td><td class="mark">{{ $row->title_mark }}</td><td class="note">{{ $row->note }}</td>
          </tr>
        @endforeach
      @endif
    @endfor
    </tbody>
  </table>
</body>
</html>
