@extends('layouts.app')

@section('content')
<div class="container">
  <<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">大会成績 登録フォーム</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
        <a href="{{ route('tournament_results.index') }}" id="btn-cancel" class="btn btn-outline-dark">
        キャンセル
        </a>
        <button type="button" id="btn-bulk" class="btn btn-warning">この大会の一括登録</button>
    </div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form action="{{ route('tournament_results.store') }}" method="POST">
    @csrf

    <div class="mb-3">
      <label>大会</label>
      <select name="tournament_id" class="form-control" required id="tournament_id">
        <option value="">-- 大会を選択 --</option>
        @foreach ($tournaments as $t)
          <option value="{{ $t->id }}" {{ old('tournament_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
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
          {{-- 候補値は「ライセンス + スペース + 氏名」両方でヒットできる形式に --}}
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

    <button type="submit" class="btn btn-primary">登録</button>
  </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const tournamentSelect = document.getElementById('tournament_id');
  const bulkBtn  = document.getElementById('btn-bulk');
  const cancelBtn= document.getElementById('btn-cancel');

  // 一括登録遷移
  const bulkBase = @json(route('tournament_results.batchCreate'));

  // キャンセル遷移（大会選択済みならその大会の成績一覧、未選択なら大会一覧）
  const showTpl  = @json(url('/tournaments/__ID__/results'));
  const listUrl  = @json(route('tournament_results.index'));

  const updateBulk = () => { bulkBtn.disabled = !tournamentSelect.value; };
  const updateCancel = () => {
    const tid = tournamentSelect.value;
    cancelBtn.href = tid ? showTpl.replace('__ID__', tid) : listUrl;
  };

  updateBulk(); updateCancel();
  tournamentSelect.addEventListener('change', () => { updateBulk(); updateCancel(); });

  bulkBtn.addEventListener('click', () => {
    const tid = tournamentSelect.value;
    if (!tid) { alert('先に大会を選んでください'); return; }
    window.location.href = `${bulkBase}?tournament_id=${encodeURIComponent(tid)}`;
  });
});
</script>
@endpush

