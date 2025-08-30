@extends('layouts.app')
@section('content')
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">講習レポート</h2>
    {{-- 右上ナビボタン（全スコープ共通で表示） --}}
    <div class="d-flex gap-2">
      <a href="{{ route('trainings.bulk') }}" class="btn btn-outline-secondary">講習一括管理へ戻る</a>
      <a href="{{ route('athlete.index') }}" class="btn btn-outline-dark">インデックスへ戻る</a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="GET" action="{{ route('trainings.reports', ['scope' => $scope]) }}">
    <div class="col-auto">
      <select name="scope" class="form-select"
              onchange="location.href='{{ route('trainings.reports') }}/'+this.value+'?days={{ $days }}'">
        <option value="compliant" {{ $scope=='compliant'?'selected':'' }}>受講済（有効）</option>
        <option value="missing"   {{ $scope=='missing'?'selected':'' }}>未受講</option>
        <option value="expired"   {{ $scope=='expired'?'selected':'' }}>期限切れ</option>
        <option value="expiring"  {{ $scope=='expiring'?'selected':'' }}>残り◯日以下</option>
      </select>
    </div>
    <div class="col-auto">
      <input type="number" name="days" class="form-control" value="{{ $days }}" min="1" step="1">
    </div>
    <div class="col-auto">
      <button class="btn btn-outline-secondary">適用</button>
      <a href="{{ route('trainings.reports') }}" class="btn btn-outline-dark">リセット</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>ライセンスNo</th>
          <th>氏名</th>
          <th>受講日</th>
          <th>有効期限</th>
          <th>状態</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($bowlers as $b)
          @php
            // 最新＆一つ前（どちらも存在しない可能性があるのでnullセーフ）
            $tList   = $b->mandatoryTrainings ?? collect();
            $latest  = $tList->get(0); // 最新
            $prev    = $tList->get(1); // ひとつ前

            $status = '未受講';
            $renew  = false;

            if ($latest) {
              $expires = $latest->expires_at;
              $status =
                  $expires->isPast()                           ? '期限切れ'
                : ($expires->lte(now()->addDays($days))        ? 'まもなく期限'
                : '適合');

              // ★ 更新者判定：前回の有効期限内に再受講しているか？
              //     ＝「前回のexpires_at >= 今回のcompleted_at」
              if ($prev && $prev->expires_at && $latest->completed_at
                  && $prev->expires_at->gte($latest->completed_at)) {
                $renew = true;
              }
            }
          @endphp
          <tr>
            <td><a href="{{ route('pro_bowlers.edit', $b->id) }}">{{ $b->id }}</a></td>
            <td>{{ $b->license_no }}</td>
            <td>{{ $b->name_kanji }}</td>
            <td>{{ $latest?->completed_at?->format('Y-m-d') }}</td>
            <td>{{ $latest?->expires_at?->format('Y-m-d') }}</td>
            <td>
              {{ $status }}@if($renew)（更新）@endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="mt-2">
    {{ $bowlers->withQueryString()->links() }}
  </div>
</div>
@endsection
