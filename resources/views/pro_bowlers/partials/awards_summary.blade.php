
@php
use App\Services\AwardCounter;
/** @var \App\Models\ProBowler $bowler */

$summary = AwardCounter::summaryForBowlerId((int) $bowler->id);
$counts = $summary['total'];
$confirmed = $summary['confirmed'];
$other = $summary['other'];
$confirmedRecords = $bowler->relationLoaded('confirmedRecords')
    ? $bowler->confirmedRecords
    : $bowler->confirmedRecords()
        ->orderByDesc('awarded_on')
        ->orderByDesc('id')
        ->get();
@endphp

<div class="card mb-3 shadow-sm">
  <div class="card-header fw-bold">褒章（達成数）</div>
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-2">
      <span class="badge bg-primary">パーフェクト：{{ number_format($counts['perfect']) }}</span>
      <span class="badge bg-success">7-10：{{ number_format($counts['seven_ten']) }}</span>
      <span class="badge bg-info text-dark">800シリーズ：{{ number_format($counts['eight_hundred']) }}</span>
      <span class="badge bg-dark">合計：{{ number_format($counts['total']) }}</span>
    </div>
    <div class="small text-muted">
      確認済み明細：
      パーフェクト {{ number_format($confirmed['perfect']) }}件 /
      7-10 {{ number_format($confirmed['seven_ten']) }}件 /
      800シリーズ {{ number_format($confirmed['eight_hundred']) }}件
    </div>
    @if($other['total'] > 0)
      <div class="small mt-2">
        その他過去達成数：
        パーフェクト {{ number_format($other['perfect']) }}件 /
        7-10 {{ number_format($other['seven_ten']) }}件 /
        800シリーズ {{ number_format($other['eight_hundred']) }}件
      </div>
      <div class="alert alert-light border mt-2 mb-0 py-2">
        現在確認できる大会分のみデータとして記載
      </div>
    @endif

    @if($confirmedRecords->isNotEmpty())
      <div class="table-responsive mt-3">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>種別</th>
              <th>達成年月日</th>
              <th>大会名</th>
              <th>達成ゲーム／シリーズ／フレーム</th>
              <th>公認番号</th>
              <th>根拠</th>
              @if($isAdmin ?? false)
                <th class="text-nowrap">操作</th>
              @endif
            </tr>
          </thead>
          <tbody>
            @foreach($confirmedRecords as $record)
              <tr>
                <td>{{ $record->record_type_label }}</td>
                <td>{{ optional($record->awarded_on)->format('Y/m/d') ?: '未確認' }}</td>
                <td>
                  {{ $record->tournament_name }}
                  @if($record->stage)
                    <div class="small text-muted">{{ $record->stage }}{{ $record->shift ? ' / '.$record->shift : '' }}</div>
                  @endif
                </td>
                <td>
                  @if($record->record_type === 'eight_hundred')
                    {{ $record->series_label ?: $record->game_numbers ?: '未確認' }}
                    @if($record->series_total !== null)
                      <div class="small">3G合計 {{ number_format($record->series_total) }}</div>
                    @endif
                  @else
                    {{ $record->game_numbers ?: '未確認' }}
                    @if($record->frame_number)
                      <div class="small">{{ $record->frame_number }}</div>
                    @endif
                  @endif
                </td>
                <td>{{ $record->certification_number ?: '未設定' }}</td>
                <td>
                  @if($record->source_url)
                    <a href="{{ $record->source_url }}" target="_blank" rel="noopener">公式資料</a>
                  @else
                    {{ $record->source_label ?: '-' }}
                  @endif
                </td>
                @if($isAdmin ?? false)
                  <td class="text-nowrap">
                    <a
                      href="{{ route('record_types.edit', ['record_type' => $record->id, 'return_to' => 'pro_bowler_edit']) }}"
                      class="btn btn-sm btn-outline-primary"
                    >編集</a>
                    <button
                      type="submit"
                      form="achievement-del-{{ $record->id }}"
                      class="btn btn-sm btn-outline-danger"
                      onclick="return confirm('この褒章明細を削除しますか？ 公認総数は履歴保護のため減りません。')"
                    >削除</button>
                  </td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
