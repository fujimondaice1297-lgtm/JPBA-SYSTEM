@extends('layouts.app')

@section('content')
  <h1>{{ $group->exists ? 'グループ設定' : 'グループ作成' }}</h1>

  {{-- エラー表示 --}}
  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">保存できませんでした：</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $group->exists ? route('pro_groups.update',$group) : route('pro_groups.store') }}">
    @csrf
    @if($group->exists) @method('PUT') @endif

    {{-- 1) プリセット（選ぶだけ） --}}
    <div class="mb-3">
      <label class="form-label">対象（プリセット）</label>
      <div class="d-flex gap-2 flex-wrap">
        <select class="form-select" name="preset" id="preset" style="max-width: 320px;">
          <option value="">（指定しない）</option>
          <option value="gender-m" {{ old('preset')=='gender-m'?'selected':'' }}>男子プロ</option>
          <option value="gender-f" {{ old('preset')=='gender-f'?'selected':'' }}>女子プロ</option>
          <option value="district-leader" {{ old('preset')=='district-leader'?'selected':'' }}>地区長</option>
          <option value="title-holder" {{ old('preset')=='title-holder'?'selected':'' }}>タイトルホルダー</option>
          <option value="license-a" {{ old('preset')=='license-a'?'selected':'' }}>A級ライセンス保有</option>
          <option value="instructor-b" {{ old('preset')=='instructor-b'?'selected':'' }}>B級インストラクター</option>
          <option value="instructor-c-only" {{ old('preset')=='instructor-c-only'?'selected':'' }}>C級のみ</option>
          <option value="training-missing-or-expired" {{ old('preset')=='training-missing-or-expired'?'selected':'' }}>講習 未受講/期限切れ</option>
          <option value="dues-unpaid-this-year" {{ old('preset')=='dues-unpaid-this-year'?'selected':'' }}>年会費 未納（今年）</option>
          <option value="district" {{ old('preset')=='district'?'selected':'' }}>（選択）該当地区のプロ</option>
          <option value="tournament" {{ old('preset')=='tournament'?'selected':'' }}>（選択）大会参加者</option>
        </select>

        {{-- プリセット補助入力 --}}
        <select class="form-select" name="preset_district_id" id="preset_district" style="max-width: 260px; display:none;">
          <option value="">地区を選択</option>
          @foreach($districts as $d)
            <option value="{{ $d->id }}" {{ (old('preset_district_id')==$d->id || ($preset_district_id??null)==$d->id) ? 'selected':'' }}>
              {{ $d->label }}（ID:{{ $d->id }}）
            </option>
          @endforeach
        </select>

        <select class="form-select" name="preset_tournament_id" id="preset_tournament" style="max-width: 360px; display:none;">
          <option value="">大会を選択</option>
          @foreach($tournaments as $t)
            <option value="{{ $t->id }}" {{ (old('preset_tournament_id')==$t->id || ($preset_tournament_id??null)==$t->id) ? 'selected':'' }}>
              [{{ $t->id }}] {{ $t->year }} {{ $t->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-text">選ぶだけでOK。必要なら「地区」か「大会」を選択。</div>
    </div>

    {{-- 2) 名前だけ入れればOK（キーは隠して自動生成） --}}
    <input type="hidden" name="key" value="{{ old('key', $group->key) }}">
    <div class="mb-3">
      <label class="form-label">名称</label>
      <input class="form-control" name="name" value="{{ old('name', $group->name) }}" placeholder="例: 男子選手会">
      <div class="form-text">キーは自動設定されます。</div>
    </div>

    {{-- 3) アクション（何をするか） --}}
    <div class="mb-3">
      <div class="form-label">このグループに対して行うこと</div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="action_mypage" id="action_mypage" value="1"
               {{ old('action_mypage', $group->action_mypage ?? false) ? 'checked':'' }}>
        <label class="form-check-label" for="action_mypage">マイページに案内を表示</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="action_email" id="action_email" value="1"
               {{ old('action_email', $group->action_email ?? false) ? 'checked':'' }}>
        <label class="form-check-label" for="action_email">一括メール送信（準備中）</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="action_postal" id="action_postal" value="1"
               {{ old('action_postal', $group->action_postal ?? false) ? 'checked':'' }}>
        <label class="form-check-label" for="action_postal">郵送リスト（CSV出力）</label>
      </div>
    </div>

    {{-- 4) 詳細設定（普段は触らない） --}}
    <details class="mb-3">
      <summary class="mb-2">詳細設定（必要な場合のみ開く）</summary>

      <div class="mb-2">
        <label class="form-label">種別</label>
        <select class="form-select" name="type">
          <option value="rule" {{ old('type',$group->type)=='rule'?'selected':'' }}>ルール（条件で自動判定）</option>
          <option value="snapshot" {{ old('type',$group->type)=='snapshot'?'selected':'' }}>スナップショット（その瞬間を固定）</option>
        </select>
        <div class="form-text">
          ルール＝性別/資格/役職など。<br>
          スナップショット＝大会参加者などを「その時点で固定」。将来は自動付与にも対応予定。
        </div>
      </div>

      <div class="mb-2">
        <label class="form-label">保持期間</label>
        <select class="form-select" name="retention">
          <option value="forever" {{ old('retention',$group->retention)=='forever'?'selected':'' }}>永続</option>
          <option value="fye"     {{ old('retention',$group->retention)=='fye'?'selected':'' }}>年度末まで</option>
          <option value="until"   {{ old('retention',$group->retention)=='until'?'selected':'' }}>指定日まで</option>
        </select>
      </div>

      <div class="mb-2">
        <label class="form-label">有効期限（retention=指定日の場合）</label>
        <input type="date" class="form-control" name="expires_at" value="{{ old('expires_at', optional($group->expires_at)->format('Y-m-d')) }}">
      </div>

      <div class="mb-2">
        <label class="form-label">ルール（上級者向け・JSON直接編集）</label>
        <textarea class="form-control" name="rule_json" rows="6"
          placeholder='{"attr":"sex","eq":1}'>{{ old('rule_json', $group->rule_json ? json_encode($group->rule_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
      </div>
    </details>

    <button class="btn btn-primary">保存</button>
  </form>

  <script>
    (function(){
      const presetSel = document.getElementById('preset');
      const district = document.getElementById('preset_district');
      const tourn    = document.getElementById('preset_tournament');
      function refresh(){
        const v = presetSel.value;
        district.style.display = (v==='district')   ? '' : 'none';
        tourn.style.display    = (v==='tournament') ? '' : 'none';
      }
      presetSel?.addEventListener('change', refresh);
      refresh();
    })();
  </script>
@endsection
