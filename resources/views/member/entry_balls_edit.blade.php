{{-- resources/views/member/entry_balls_edit.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
  <h2>大会使用ボール登録</h2>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="mb-3 d-flex gap-2">
    <a href="{{ route('tournament.entry.select') }}" class="btn btn-secondary">大会エントリー選択へ戻る</a>
    <a href="{{ route('tournaments.index') }}" class="btn btn-outline-secondary">大会一覧へ</a>
  </div>

  <div class="mb-3">
    <div><strong>大会名：</strong>{{ $entry->tournament->name }}</div>
    <div><strong>エントリー状況：</strong>{{ $entry->status === 'entry' ? 'エントリー済み' : $entry->status }}</div>
    <div>
      <strong>登録状況：</strong> 登録済 {{ $existingCount }} 個 / 残り {{ max(0, 12 - $existingCount) }} 個
      @if($inspectionRequired)
        <span class="badge bg-warning text-dark ms-2">検量証必須</span>
        <small class="text-muted d-block">
          ※ 検量証未入力のボールは <strong>仮登録</strong> として受け付けます（検量証番号を後で入力すると本登録扱い）
        </small>
      @endif
    </div>
  </div>

  <form method="POST" action="{{ route('member.entries.balls.store', $entry->id) }}">
    @csrf

    <div class="card">
      <div class="card-header">自分の使用ボール（有効期限内のみ）</div>
      <div class="card-body">
        @if($remaining <= 0)
          <div class="alert alert-info">すでに 12 個登録済みです。これ以上は追加できません。</div>
        @endif

        @forelse($usedBalls as $ball)
          @php
            $isLinked = in_array($ball->id, $linkedIds, true);
            $label = ($ball->approvedBall?->manufacturer ? $ball->approvedBall->manufacturer.' - ' : '')
                    .($ball->approvedBall?->name ?? '不明').' / SN: '.$ball->serial_number;
          @endphp

          <div class="form-check mb-2">
            <input class="form-check-input used-ball-checkbox"
                   type="checkbox"
                   name="used_ball_ids[]"
                   value="{{ $ball->id }}"
                   id="ball{{ $ball->id }}"
                   {{ $isLinked ? 'checked disabled' : '' }}
                   {{ $remaining <= 0 ? 'disabled' : '' }}>
            <label class="form-check-label" for="ball{{ $ball->id }}">
              {{ $label }}

              @if($isLinked)
                <span class="badge bg-secondary">登録済</span>
              @endif

              @if (!empty($ball->inspection_number))
                <span class="badge bg-info">検量証OK</span>
              @else
                <span class="badge bg-warning text-dark">仮登録</span>
              @endif

              @if (!empty($ball->expires_at))
                <span class="text-muted ms-2">(有効期限: {{ optional($ball->expires_at)->format('Y-m-d') }})</span>
              @endif
            </label>
          </div>
        @empty
          <div class="text-muted">使用ボールがありません。</div>
        @endforelse
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary" {{ $remaining <= 0 ? 'disabled' : '' }}>登録する</button>

      {{-- 検量証なしの仮登録もできる「ボール登録」画面へショートカット --}}
      <a class="btn btn-outline-success"
         href="{{ route('used_balls.create', ['license_no' => auth()->user()->proBowler?->license_no]) }}">
        ＋ ボール登録（仮登録可）
      </a>

      <a href="{{ route('tournament.entry.select') }}" class="btn btn-secondary">エントリー選択へ戻る</a>
    </div>
  </form>
</div>

@push('scripts')
<script>
  // 合計12個の上限をフロント側でも保護
  document.addEventListener('DOMContentLoaded', () => {
    const MAX = 12;
    const already = {{ $existingCount }};
    const remain = Math.max(0, MAX - already);
    if (remain <= 0) return;

    let currentNew = 0;
    const boxes = Array.from(document.querySelectorAll('.used-ball-checkbox:not(:disabled):not(:checked)'));

    boxes.forEach(b => {
      b.addEventListener('change', (e) => {
        if (e.target.checked) {
          currentNew++;
          if (currentNew > remain) {
            e.target.checked = false;
            currentNew--;
            alert('1大会で登録できるのは最大12個までです。');
          }
        } else {
          currentNew--;
        }
      });
    });
  });
</script>
@endpush
@endsection
