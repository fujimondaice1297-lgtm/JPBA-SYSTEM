@extends('layouts.app')

@section('content')
<div class="container py-3" style="max-width: 960px;">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
      <h2 class="mb-1">大会成績登録</h2>
      <div class="text-muted small">
        配分設定済みの大会は、保存時にポイント・賞金が自動反映されます。
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('tournament_results.index') }}" class="btn btn-secondary">大会検索へ戻る</a>
      <a href="#" id="btn-cancel" class="btn btn-outline-dark">この大会の成績一覧へ戻る</a>
      <button type="button" id="btn-bulk" class="btn btn-warning" disabled>この大会の一括登録</button>
    </div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>入力内容に誤りがあります。</strong>
      <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="alert alert-info py-2">
    先に <strong>ポイント配分</strong> と <strong>賞金配分</strong> を登録しておくと、
    この画面の保存時にその順位の値が自動で入ります。<br>
    配分を後から変更した場合だけ、成績一覧で <strong>賞金・ポイント再計算</strong> を実行してください。
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form id="result-form" method="POST" action="#">
        @csrf

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">大会 <span class="text-danger">*</span></label>
            <select name="tournament_id" class="form-select" required id="tournament_id">
              <option value="">-- 大会を選択 --</option>
              @foreach ($tournaments as $t)
                <option value="{{ $t->id }}" {{ old('tournament_id') == $t->id ? 'selected' : '' }}>
                  {{ $t->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">順位 <span class="text-danger">*</span></label>
            <input type="number" name="ranking" class="form-control" value="{{ old('ranking') }}" required min="1">
          </div>

          <div class="col-md-3">
            <label class="form-label">年度 <span class="text-danger">*</span></label>
            <input type="number" name="ranking_year" class="form-control" value="{{ old('ranking_year', date('Y')) }}" required>
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

          <div class="col-12">
            <label class="form-label d-block mb-2">選手区分 <span class="text-danger">*</span></label>
            <div class="d-flex flex-wrap gap-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="player_mode" id="mode_pro" value="pro"
                       {{ old('player_mode','pro')==='pro' ? 'checked' : '' }}>
                <label class="form-check-label" for="mode_pro">プロ</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="player_mode" id="mode_ama" value="ama"
                       {{ old('player_mode')==='ama' ? 'checked' : '' }}>
                <label class="form-check-label" for="mode_ama">アマチュア</label>
              </div>
            </div>
          </div>

          <div class="col-12" id="pro_block">
            <label class="form-label">プロ選手（ライセンスNo または 氏名で検索）</label>
            <input type="text"
                   name="pro_key"
                   class="form-control"
                   list="pro-candidates"
                   placeholder="例）M0123 / F00456 / 山田太郎"
                   value="{{ old('pro_key') }}">
            <datalist id="pro-candidates">
              @foreach ($players as $p)
                <option value="{{ $p->license_no }} {{ $p->name }}"></option>
                <option value="{{ $p->name }}"></option>
              @endforeach
            </datalist>
            <div class="form-text">候補から選ぶか、そのまま入力してください。</div>
          </div>

          <div class="col-12 d-none" id="ama_block">
            <label class="form-label">アマチュア選手名</label>
            <input type="text" name="amateur_name" class="form-control" value="{{ old('amateur_name') }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">トータルピン <span class="text-danger">*</span></label>
            <input type="number" name="total_pin" id="total_pin" class="form-control" value="{{ old('total_pin') }}" required min="0">
          </div>

          <div class="col-md-6">
            <label class="form-label">投球ゲーム数 <span class="text-danger">*</span></label>
            <input type="number" name="games" id="games" class="form-control" value="{{ old('games') }}" required min="1">
          </div>

          <div class="col-12">
            <div class="border rounded p-3 bg-light">
              <div class="small text-muted mb-1">平均目安（保存時に自動計算）</div>
              <div id="average-preview" class="fw-bold">-</div>
            </div>
          </div>
        </div>

        <div class="mt-4 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary" id="btn-submit" disabled>登録する</button>
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
  const form       = document.getElementById('result-form');
  const tournament = document.getElementById('tournament_id');
  const bulkBtn    = document.getElementById('btn-bulk');
  const cancelBtn  = document.getElementById('btn-cancel');
  const submitBtn  = document.getElementById('btn-submit');

  const helperWrap    = document.getElementById('tournament-helper-wrap');
  const helperResults = document.getElementById('helper-results');
  const helperPoint   = document.getElementById('helper-point');
  const helperPrize   = document.getElementById('helper-prize');

  const totalPinInput = document.getElementById('total_pin');
  const gamesInput    = document.getElementById('games');
  const avgPreview    = document.getElementById('average-preview');

  const postTpl    = @json(url('/tournaments/__ID__/results'));
  const indexTpl   = @json(url('/tournaments/__ID__/results'));
  const bulkBase   = @json(route('tournament_results.batchCreate'));
  const pointTpl   = @json(route('tournaments.point_distributions.create', ['tournament' => '__ID__']));
  const prizeTpl   = @json(route('tournaments.prize_distributions.create', ['tournament' => '__ID__']));
  const listUrl    = @json(route('tournament_results.index'));

  function applyTournament(id){
    if(id){
      form.action    = postTpl.replace('__ID__', id);
      cancelBtn.href = indexTpl.replace('__ID__', id);
      bulkBtn.disabled   = false;
      submitBtn.disabled = false;

      helperWrap.classList.remove('d-none');
      helperResults.href = indexTpl.replace('__ID__', id);
      helperPoint.href   = pointTpl.replace('__ID__', id);
      helperPrize.href   = prizeTpl.replace('__ID__', id);
    }else{
      form.action    = '#';
      cancelBtn.href = listUrl;
      bulkBtn.disabled   = true;
      submitBtn.disabled = true;

      helperWrap.classList.add('d-none');
      helperResults.href = '#';
      helperPoint.href   = '#';
      helperPrize.href   = '#';
    }
  }

  function updateAverage(){
    const totalPin = parseFloat(totalPinInput.value || 0);
    const games    = parseFloat(gamesInput.value || 0);

    if (totalPin > 0 && games > 0) {
      avgPreview.textContent = (totalPin / games).toFixed(2);
    } else {
      avgPreview.textContent = '-';
    }
  }

  (function preselectFromUrl(){
    const m = location.pathname.match(/\/tournaments\/(\d+)\/results\/create/);
    if(m){
      const id = m[1];
      const opt = tournament.querySelector(`option[value="${id}"]`);
      if(opt) opt.selected = true;
      applyTournament(id);
    }else{
      applyTournament(tournament.value);
    }
  })();

  tournament.addEventListener('change', e => applyTournament(e.target.value));

  bulkBtn.addEventListener('click', () => {
    const id = tournament.value;
    if(!id){ alert('先に大会を選んでください'); return; }
    window.location.href = `${bulkBase}?tournament_id=${encodeURIComponent(id)}`;
  });

  const proBlock = document.getElementById('pro_block');
  const amaBlock = document.getElementById('ama_block');

  function toggleMode(){
    const mode = document.querySelector('input[name="player_mode"]:checked')?.value || 'pro';
    if(mode === 'pro'){
      proBlock.classList.remove('d-none');
      amaBlock.classList.add('d-none');
    }else{
      proBlock.classList.add('d-none');
      amaBlock.classList.remove('d-none');
    }
  }

  document.querySelectorAll('input[name="player_mode"]').forEach(r => {
    r.addEventListener('change', toggleMode);
  });

  [totalPinInput, gamesInput].forEach(el => {
    el.addEventListener('input', updateAverage);
  });

  toggleMode();
  updateAverage();
});
</script>
@endpush