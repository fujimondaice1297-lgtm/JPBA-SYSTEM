
@php
use App\Services\AwardCounter;
/** @var \App\Models\ProBowler $bowler */

// カウンタ列が読めるならそれ、なければサービスで都度集計
$hasCached = isset($bowler->perfect_count) && isset($bowler->seven_ten_count) && isset($bowler->eight_hundred_count);
$counts = $hasCached
    ? [
        'perfect'       => (int)($bowler->perfect_count ?? 0),
        'seven_ten'     => (int)($bowler->seven_ten_count ?? 0),
        'eight_hundred' => (int)($bowler->eight_hundred_count ?? 0),
      ]
    : AwardCounter::countsForBowlerId($bowler->id);

$counts['total'] = ($counts['perfect'] ?? 0) + ($counts['seven_ten'] ?? 0) + ($counts['eight_hundred'] ?? 0);
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
    {{-- 必要なら詳細テーブルを置く場所 --}}
  </div>
</div>
