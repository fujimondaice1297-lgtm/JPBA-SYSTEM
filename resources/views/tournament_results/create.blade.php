@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">大会成績 登録フォーム</h2>
    <div class="d-flex gap-2">
      <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
      <a href="#" id="btn-cancel" class="btn btn-outline-dark">キャンセル</a>
      <button type="button" id="btn-bulk" class="btn btn-warning" disabled>この大会の一括登録</button>
    </div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  {{-- ★action は JS で大会IDを選んだ時に /tournaments/{id}/results（POST）へ差し替え --}}
  <form id="result-form" method="POST" action="#">
    @csrf

    <div class="mb-3">
      <label>大会</label>
      <select name="tournament_id" class="form-control" required id="tournament_id">
        <option value="">-- 大会を選択 --</option>
        @foreach ($tournaments as $t)
          <option value="{{ $t->id }}" {{ old('tournament_id') == $t->id ? 'selected' : '' }}>
            {{ $t->name }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- 選手区分 --}}
    <div class="mb-2">
      <label class="form-label d-block">選手区分</label>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="player_mode" id="mode_pro" value="pro"
               {{ old('player_mode','pro')==='pro' ? 'checked' : '' }}>
        <label class="form-check-label" for="mode_pro">プロ</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="player_mode" id="mode_ama" value="ama"
               {{ old('player_mode')==='ama' ? 'checked' : '' }}>
        <label class="form-check-label" for="mode_ama">アマチュア</label>
      </div>
    </div>

    {{-- プロ用：ライセンス／氏名検索（datalist） --}}
    <div class="mb-3" id="pro_block">
      <label>プロ選手（ライセンスNo または 氏名で検索）</label>
      <input type="text" name="pro_key" class="form-control" list="pro-candidates"
             placeholder="例）M0123 / F00456 / m000123 / 山田太郎"
             value="{{ old('pro_key') }}">
      <datalist id="pro-candidates">
        @foreach ($players as $p)
          <option value="{{ $p->license_no }} {{ $p->name }}"></option>
          <option value="{{ $p->name }}"></option>
        @endforeach
      </datalist>
      <small class="text-muted">候補から選ぶか、そのまま入力してください。</small>
    </div>

    {{-- アマ用：氏名直入力 --}}
    <div class="mb-3 d-none" id="ama_block">
      <label>アマチュア選手名</label>
      <input type="text" name="amateur_name" class="form-control" value="{{ old('amateur_name') }}">
    </div>

    <div class="mb-3">
      <label>順位</label>
      <input type="number" name="ranking" class="form-control" value="{{ old('ranking') }}" required>
    </div>

    <div class="mb-3">
      <label>トータルピン</label>
      <input type="number" name="total_pin" class="form-control" value="{{ old('total_pin') }}" required>
    </div>

    <div class="mb-3">
      <label>投球ゲーム数</label>
      <input type="number" name="games" class="form-control" value="{{ old('games') }}" required>
    </div>

    <div class="mb-3">
      <label>年度</label>
      <input type="number" name="ranking_year" class="form-control" value="{{ old('ranking_year', date('Y')) }}" required>
    </div>

    <button type="submit" class="btn btn-primary" id="btn-submit" disabled>登録</button>
  </form>
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

  // ★ 既存ルートに依存しないよう URL 文字列テンプレートで構築
  const postTpl  = @json(url('/tournaments/__ID__/results'));     // POST: store
  const indexTpl = @json(url('/tournaments/__ID__/results'));     // GET : index（一覧）
  const tourList = @json(route('tournaments.index'));             // 大会一覧（大会未選択のキャンセル先）

  // 一括登録入口（既存の機能を使う前提。クエリで tournament_id を渡す）
  const bulkBase = @json(route('tournament_results.batchCreate'));

  function applyTournament(id){
    if(id){
      form.action   = postTpl.replace('__ID__', id);
      cancelBtn.href= indexTpl.replace('__ID__', id);
      bulkBtn.disabled  = false;
      submitBtn.disabled= false;
    }else{
      form.action   = '#';
      cancelBtn.href= tourList;
      bulkBtn.disabled  = true;
      submitBtn.disabled= true;
    }
  }

  // URL が /tournaments/{id}/results/create なら初期大会IDを自動反映
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

  // プロ／アマの切り替えで入力欄を出し分け
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
  document.querySelectorAll('input[name="player_mode"]').forEach(r => r.addEventListener('change', toggleMode));
  toggleMode();
});
</script>
@endpush
