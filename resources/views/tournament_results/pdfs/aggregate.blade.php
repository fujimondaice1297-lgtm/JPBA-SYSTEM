<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>{{ $tournament->name }} {{ $definition->name }}</title>
  <style>
    @page { margin: 14mm 10mm; }
    body { font-family: "ipaexg", "DejaVu Sans", sans-serif; color:#172033; font-size:9px; }
    h1 { margin:0 0 3px; font-size:16px; color:#174a8b; }
    .meta { margin-bottom:10px; color:#4b5563; }
    .mode { float:right; border:1px solid #174a8b; padding:3px 7px; color:#174a8b; }
    table { width:100%; border-collapse:collapse; table-layout:fixed; }
    th, td { border:1px solid #aeb8c6; padding:4px; text-align:right; vertical-align:top; }
    th { background:#eaf0f7; color:#24364d; }
    .rank { width:35px; }
    .subject { width:210px; text-align:left; }
    .status { width:48px; }
    .members { margin-top:2px; color:#5f6b7a; font-size:8px; }
    .note { margin-top:8px; color:#5f6b7a; }
  </style>
</head>
<body>
  <div class="mode">{{ $mode === 'official' ? '正式成績' : '速報' }}</div>
  <h1>{{ $definition->name }}</h1>
  <div class="meta">
    {{ $tournament->name }}　更新: {{ optional($updatedAt)->format('Y/m/d H:i') }}
  </div>

  <table>
    <thead>
      <tr>
        <th class="rank">順位</th>
        <th class="subject">{{ $definition->subject_type === 'group' ? 'チーム／ダブルス' : '選手' }}</th>
        @foreach($sources as $source)<th>{{ $source['label'] }}</th>@endforeach
        <th>G</th>
        <th>トータル</th>
        <th>AVG</th>
        <th class="status">状態</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $row)
        @php
          $license = $row['pro_bowler_id']
              ? \App\Support\PublicLicenseNumber::format($row['pro_bowler_license_no'] ?? '')
              : '';
          $members = $groupMembers[(int)($row['competitor_group_id'] ?? 0)] ?? [];
        @endphp
        <tr>
          <td>{{ $row['is_complete'] ? ($row['ranking'] ?: '-') : '-' }}</td>
          <td class="subject">
            <strong>{{ $row['display_name'] }}</strong>@if($license !== '')（{{ $license }}）@endif
            @if($members)<div class="members">{{ implode('／', $members) }}</div>@endif
          </td>
          @foreach($sources as $source)
            @php $part = data_get($row, 'source_breakdown.' . $source['id']); @endphp
            <td>{{ $part ? number_format((int)($part['total_pin'] ?? 0)) : '-' }}</td>
          @endforeach
          <td>{{ (int)$row['games'] }}</td>
          <td><strong>{{ number_format((int)$row['total_pin']) }}</strong></td>
          <td>{{ $row['average'] !== null ? number_format((float)$row['average'], 2) : '-' }}</td>
          <td>{{ $row['is_complete'] ? '完了' : '未完了' }}</td>
        </tr>
      @empty
        <tr><td colspan="{{ count($sources) + 6 }}">対象スコアはありません。</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="note">
    チーム／ダブルス／オールエベンツの競技別集計は、個人の公式ポイント・賞金・タイトルへ重複計上しません。
  </div>
</body>
</html>
