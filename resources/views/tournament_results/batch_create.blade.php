@extends('layouts.app')

@section('content')
<div class="container py-3" style="max-width: 1100px;">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
      <h2 class="mb-1">大会成績 一括登録</h2>
      <div class="text-muted small">
        まとめて登録する画面です。配分設定済みの順位は、保存時にポイント・賞金が自動反映されます。
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('tournament_results.index') }}" class="btn btn-secondary">大会検索へ戻る</a>
      <a href="{{ route('tournament_results.index') }}" id="btn-cancel" class="btn btn-outline-dark">この大会の成績一覧へ戻る</a>
    </div>
  </div>

  <div class="alert alert-info py-2">
    一括登録でも、配分済みの順位は保存時に自動反映されます。<br>
    配分を後から変更した場合だけ、成績一覧で <strong>賞金・ポイント再計算</strong> を実行してください。
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form action="{{ route('tournament_results.batchStore') }}" method="POST">
        @csrf

        <div class="row g-3 mb-4">
          <div class="col-md-8">
            <label class="form-label">大会 <span class="text-danger">*</span></label>
            <select name="tournament_id" class="form-select" required id="tournament_id">
              <option value="">選択してください</option>
              @foreach ($tournaments as $t)
                <option value="{{ $t->id }}" @selected(isset($tournamentId) && $t->id == $tournamentId)>
                  {{ $t->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">年度 <span class="text-danger">*</span></label>
            <input type="number" name="ranking_year" class="form-control" value="{{ date('Y') }}" required>
          </div>

          <div class="col-12 d-none" id="tournament-helper-wrap">
            <div class="border rounded p-3 bg-light">
              <div class="small text-muted mb-2">選択中大会の補助導線</div>
              <div class="d-flex flex-wrap gap-2">
                <a href="#" id="helper-results" class="btn btn-outline-secondary btn-sm">成績一覧</a>
                <a href="#" id="helper-point" class="btn btn-outline-danger btn-sm">ポイント配分</a>
                <a href="#" id="helper-prize" class="btn btn-outline-warning btn-sm">賞金配分</a>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          @for ($i = 0; $i < 10; $i++)
            <div class="col-12">
              <div class="card border js-entry-row">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <strong>選手 {{ $i + 1 }}</strong>
                  <div class="d-flex gap-3">
                    <div class="form-check form-check-inline mb-0">
                      <input class="form-check-input js-mode-pro" type="radio" name="rows[{{ $i }}][player_mode]" value="pro" checked>
                      <label class="form-check-label">プロ</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                      <input class="form-check-input js-mode-ama" type="radio" name="rows[{{ $i }}][player_mode]" value="ama">
                      <label class="form-check-label">アマチュア</label>
                    </div>
                  </div>
                </div>

                <div class="card-body">
                  <div class="row g-3">
                    <div class="col-md-6 js-pro-block">
                      <label class="form-label">プロ選手（ライセンス/氏名）</label>
                      <input type="text"
                             name="rows[{{ $i }}][pro_key]"
                             class="form-control"
                             list="pro-candidates"
                             placeholder="例）M0123 / F00456 / 山田太郎">
                    </div>

                    <div class="col-md-6 js-ama-block d-none">
                      <label class="form-label">アマチュア選手名</label>
                      <input type="text" name="rows[{{ $i }}][amateur_name]" class="form-control">
                    </div>

                    <div class="col-md-2">
                      <label class="form-label">順位</label>
                      <input type="number" name="rows[{{ $i }}][ranking]" class="form-control" min="1">
                    </div>

                    <div class="col-md-3">
                      <label class="form-label">トータルピン</label>
                      <input type="number" name="rows[{{ $i }}][total_pin]" class="form-control js-total-pin" min="0">
                    </div>

                    <div class="col-md-2">
                      <label class="form-label">ゲーム数</label>
                      <input type="number" name="rows[{{ $i }}][games]" class="form-control js-games" min="1">
                    </div>

                    <div class="col-md-5">
                      <label class="form-label">平均目安</label>
                      <div class="border rounded p-2 bg-light fw-bold js-average-preview">-</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          @endfor
        </div>

        <datalist id="pro-candidates">
          @foreach ($players as $p)
            <option value="{{ $p->license_no }} {{ $p->name }}"></option>
            <option value="{{ $p->name }}"></option>
          @endforeach
        </datalist>

        <div class="mt-4 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary">一括登録する</button>
          <a href="{{ route('tournament_results.index') }}" class="btn btn-outline-secondary">大会検索へ戻る</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const sel        = document.getElementById('tournament_id');
  const cancel     = document.getElementById('btn-cancel');
  const helperWrap = document.getElementById('tournament-helper-wrap');
  const helperResults = document.getElementById('helper-results');
  const helperPoint   = document.getElementById('helper-point');
  const helperPrize   = document.getElementById('helper-prize');

  const showTpl  = @json(url('/tournaments/__ID__/results'));
  const listUrl  = @json(route('tournament_results.index'));
  const pointTpl = @json(route('tournaments.point_distributions.create', ['tournament' => '__ID__']));
  const prizeTpl = @json(route('tournaments.prize_distributions.create', ['tournament' => '__ID__']));

  const syncTournamentLinks = () => {
    if (sel.value) {
      const resultsUrl = showTpl.replace('__ID__', sel.value);
      cancel.href = resultsUrl;
      helperWrap.classList.remove('d-none');
      helperResults.href = resultsUrl;
      helperPoint.href = pointTpl.replace('__ID__', sel.value);
      helperPrize.href = prizeTpl.replace('__ID__', sel.value);
    } else {
      cancel.href = listUrl;
      helperWrap.classList.add('d-none');
      helperResults.href = '#';
      helperPoint.href = '#';
      helperPrize.href = '#';
    }
  };

  syncTournamentLinks();
  sel.addEventListener('change', syncTournamentLinks);

  document.querySelectorAll('.js-entry-row').forEach(row => {
    const proRadio = row.querySelector('.js-mode-pro');
    const amaRadio = row.querySelector('.js-mode-ama');
    const proBlk   = row.querySelector('.js-pro-block');
    const amaBlk   = row.querySelector('.js-ama-block');
    const totalPin = row.querySelector('.js-total-pin');
    const games    = row.querySelector('.js-games');
    const preview  = row.querySelector('.js-average-preview');

    const toggle = () => {
      const isPro = proRadio.checked;
      proBlk.classList.toggle('d-none', !isPro);
      amaBlk.classList.toggle('d-none', isPro);
    };

    const updateAverage = () => {
      const tp = parseFloat(totalPin.value || 0);
      const gm = parseFloat(games.value || 0);
      preview.textContent = (tp > 0 && gm > 0) ? (tp / gm).toFixed(2) : '-';
    };

    [proRadio, amaRadio].forEach(r => r.addEventListener('change', toggle));
    [totalPin, games].forEach(el => el.addEventListener('input', updateAverage));

    toggle();
    updateAverage();
  });
});
</script>
@endpush