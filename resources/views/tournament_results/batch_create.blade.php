@extends('layouts.app')

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">大会成績 一括登録</h2>
    <div class="d-flex gap-2">
        <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
        <a href="{{ route('tournament_results.index') }}" id="btn-cancel" class="btn btn-outline-dark">キャンセル</a>
    </div>
  </div>

  <form action="{{ route('tournament_results.batchStore') }}" method="POST">
    @csrf

    <div class="mb-3">
      <label>大会</label>
      <select name="tournament_id" class="form-control" required>
        <option value="">選択してください</option>
        @foreach ($tournaments as $t)
          <option value="{{ $t->id }}" @selected(isset($tournamentId) && $t->id == $tournamentId)>{{ $t->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label>年度</label>
      <input type="number" name="ranking_year" class="form-control" value="{{ date('Y') }}" required>
    </div>

    <hr>

    @for ($i = 0; $i < 10; $i++)
      <fieldset class="border rounded-2 p-3 mb-3">
        <legend class="float-none w-auto px-2">選手 {{ $i+1 }}</legend>

        <div class="mb-2">
          <label class="form-label d-block">選手区分</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input js-mode-pro" type="radio" name="rows[{{ $i }}][player_mode]" value="pro" checked>
            <label class="form-check-label">プロ</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input js-mode-ama" type="radio" name="rows[{{ $i }}][player_mode]" value="ama">
            <label class="form-check-label">アマチュア</label>
          </div>
        </div>

        <div class="row g-2">
          <div class="col-md-6 js-pro-block">
            <label>プロ選手（ライセンス/氏名）</label>
            <input type="text" name="rows[{{ $i }}][pro_key]" class="form-control" list="pro-candidates"
                   placeholder="例）M0123 / F00456 / 山田太郎">
          </div>
          <div class="col-md-6 js-ama-block d-none">
            <label>アマチュア選手名</label>
            <input type="text" name="rows[{{ $i }}][amateur_name]" class="form-control">
          </div>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-md-2">
            <label>順位</label>
            <input type="number" name="rows[{{ $i }}][ranking]" class="form-control">
          </div>
          <div class="col-md-3">
            <label>トータルピン</label>
            <input type="number" name="rows[{{ $i }}][total_pin]" class="form-control">
          </div>
          <div class="col-md-2">
            <label>ゲーム数</label>
            <input type="number" name="rows[{{ $i }}][games]" class="form-control">
          </div>
        </div>
      </fieldset>
    @endfor

    {{-- 候補リスト（ライセンス/氏名） --}}
    <datalist id="pro-candidates">
      @foreach ($players as $p)
        <option value="{{ $p->license_no }} {{ $p->name }}"></option>
        <option value="{{ $p->name }}"></option>
      @endforeach
    </datalist>

    <button type="submit" class="btn btn-primary">一括登録</button>
    <a href="{{ route('tournament_results.index') }}" class="btn btn-secondary">戻る</a>
  </form>
</div>
@endsection

@push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
    // プロ/アマの表示切替（既存ロジック）
    document.querySelectorAll('fieldset').forEach(fs => {
        const proRadio = fs.querySelector('.js-mode-pro');
        const amaRadio = fs.querySelector('.js-mode-ama');
        const proBlk   = fs.querySelector('.js-pro-block');
        const amaBlk   = fs.querySelector('.js-ama-block');
        const toggle   = () => {
        const isPro = proRadio.checked;
        proBlk.classList.toggle('d-none', !isPro);
        amaBlk.classList.toggle('d-none', isPro);
        };
        [proRadio, amaRadio].forEach(r => r.addEventListener('change', toggle));
        toggle();
    });

    // キャンセル遷移の自動切替
    const sel      = document.querySelector('select[name="tournament_id"]');
    const cancel   = document.getElementById('btn-cancel');
    const showTpl  = @json(url('/tournaments/__ID__/results'));
    const listUrl  = @json(route('tournament_results.index'));
    const sync     = () => { cancel.href = sel.value ? showTpl.replace('__ID__', sel.value) : listUrl; };
    sync(); sel.addEventListener('change', sync);
    });
    </script>
@endpush

