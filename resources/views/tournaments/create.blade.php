{{-- resources/views/tournaments/create.blade.php --}}
@extends('layouts.app')

@section('content')
@php
  $orgInit = old('org', []);
  if (!is_array($orgInit)) {
      $orgInit = [];
  }

  $scheduleInit = old('schedule', []);
  if (!is_array($scheduleInit)) {
      $scheduleInit = [];
  }

  $awardsInit = old('awards', []);
  if (!is_array($awardsInit)) {
      $awardsInit = [];
  }

  $resultCardsInit = old('result_cards', []);
  if (!is_array($resultCardsInit)) {
      $resultCardsInit = [];
  }
@endphp

<h2>大会を作成</h2>

@if ($errors->any())
  <div class="alert alert-danger">
    <strong>入力内容に誤りがあります：</strong>
    <ul class="mb-0 mt-2">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if (session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (!empty($prefill))
  <div class="alert alert-info">
    前回大会の内容を下書きに反映しています。<br>
    日付は空にしています。アップロード済み画像・PDFは必要に応じて見直してください。
  </div>
@endif

<form method="POST" action="{{ route('tournaments.store') }}" enctype="multipart/form-data" id="tournament-create-form">
  @csrf

  {{-- 基本情報 --}}
  <h4 data-bs-toggle="collapse" href="#t-basic" role="button" aria-expanded="true" aria-controls="t-basic">
    基本情報 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse show" id="t-basic">
    <div class="col-md-8 mb-3">
      <label class="form-label">大会名 <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
             placeholder="例：全日本選手権" value="{{ old('name') }}" required>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">大会区分 <span class="text-danger">*</span></label>
      @php $official = old('official_type','official'); @endphp
      <select name="official_type" class="form-select @error('official_type') is-invalid @enderror" required>
        <option value="official"  {{ $official==='official' ? 'selected' : '' }}>公認</option>
        <option value="approved" {{ $official==='approved'? 'selected' : '' }}>承認</option>
        <option value="other"    {{ $official==='other'   ? 'selected' : '' }}>その他</option>
      </select>
      @error('official_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">種別（男女） <span class="text-danger">*</span></label>
      @php $g = old('gender','X'); @endphp
      <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
        <option value="M" {{ $g==='M' ? 'selected' : '' }}>男子</option>
        <option value="F" {{ $g==='F' ? 'selected' : '' }}>女子</option>
        <option value="X" {{ $g==='X' ? 'selected' : '' }}>男女/未設定</option>
      </select>
      @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">タイトル区分</label>
      @php $tc = old('title_category','normal'); @endphp
      <select name="title_category" class="form-select @error('title_category') is-invalid @enderror">
        <option value="normal"        {{ $tc==='normal' ? 'selected' : '' }}>通常</option>
        <option value="season_trial" {{ $tc==='season_trial' ? 'selected' : '' }}>シーズントライアル</option>
        <option value="excluded"     {{ $tc==='excluded' ? 'selected' : '' }}>除外</option>
      </select>
      @error('title_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted">（タイトル区分：成績反映ボタンの対象分類）</small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">開始日</label>
      <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
             value="{{ old('start_date') }}">
      @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">終了日</label>
      <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
             value="{{ old('end_date') }}">
      @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">申込開始日</label>
      <input type="date" name="entry_start" class="form-control @error('entry_start') is-invalid @enderror"
             value="{{ old('entry_start') }}">
      @error('entry_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">申込締切日</label>
      <input type="date" name="entry_end" class="form-control @error('entry_end') is-invalid @enderror"
             value="{{ old('entry_end') }}">
      @error('entry_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">大会観覧</label>
      @php $sp = old('spectator_policy', ''); @endphp
      <select name="spectator_policy" class="form-select @error('spectator_policy') is-invalid @enderror">
        <option value="" {{ $sp==='' ? 'selected' : '' }}>未設定</option>
        <option value="paid" {{ $sp==='paid' ? 'selected' : '' }}>可（有料）</option>
        <option value="free" {{ $sp==='free' ? 'selected' : '' }}>可（無料）</option>
        <option value="none" {{ $sp==='none' ? 'selected' : '' }}>不可</option>
      </select>
      @error('spectator_policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label d-block">検量証必須</label>
      <input type="hidden" name="inspection_required" value="0">
      <input type="checkbox" name="inspection_required" value="1" class="form-check-input"
             {{ old('inspection_required') ? 'checked' : '' }}>
      <small class="text-muted d-block">※ チェック時：検量証未入力のボールは仮登録扱い</small>
    </div>
  </div>

  {{-- 運営 / 抽選設定 --}}
  <h4 data-bs-toggle="collapse" href="#t-operation" role="button" aria-expanded="false" aria-controls="t-operation">
    運営 / 抽選設定 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-operation">
    <div class="col-md-3 mb-3">
      <label class="form-label d-block">シフト抽選を使う</label>
      <input type="hidden" name="use_shift_draw" value="0">
      <input type="checkbox" name="use_shift_draw" value="1" class="form-check-input"
             {{ old('use_shift_draw') ? 'checked' : '' }}>
    </div>

    <div class="col-md-5 mb-3">
      <label class="form-label">シフト候補</label>
      <input type="text" name="shift_codes" class="form-control @error('shift_codes') is-invalid @enderror"
             placeholder="例：A,B,C" value="{{ old('shift_codes') }}">
      @error('shift_codes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label d-block">希望シフト受付</label>
      <input type="hidden" name="accept_shift_preference" value="0">
      <input type="checkbox" name="accept_shift_preference" value="1" class="form-check-input"
             {{ old('accept_shift_preference') ? 'checked' : '' }}>
      <small class="text-muted d-block">会員エントリー時に希望シフトを受け付けます。</small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">シフト抽選開始</label>
      <input type="datetime-local" name="shift_draw_open_at"
             class="form-control @error('shift_draw_open_at') is-invalid @enderror"
             value="{{ old('shift_draw_open_at') }}">
      @error('shift_draw_open_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">シフト抽選終了</label>
      <input type="datetime-local" name="shift_draw_close_at"
             class="form-control @error('shift_draw_close_at') is-invalid @enderror"
             value="{{ old('shift_draw_close_at') }}">
      @error('shift_draw_close_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label d-block">レーン抽選を使う</label>
      <input type="hidden" name="use_lane_draw" value="0">
      <input type="checkbox" name="use_lane_draw" value="1" class="form-check-input"
             {{ old('use_lane_draw') ? 'checked' : '' }}>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">割付方式</label>
      @php $laneMode = old('lane_assignment_mode', 'single_lane'); @endphp
      <select name="lane_assignment_mode" class="form-select @error('lane_assignment_mode') is-invalid @enderror">
        <option value="single_lane" {{ $laneMode === 'single_lane' ? 'selected' : '' }}>通常レーン割付</option>
        <option value="box" {{ $laneMode === 'box' ? 'selected' : '' }}>BOX運用</option>
      </select>
      @error('lane_assignment_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">使用レーン開始</label>
      <input type="number" name="lane_from" class="form-control @error('lane_from') is-invalid @enderror"
             value="{{ old('lane_from') }}">
      @error('lane_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">使用レーン終了</label>
      <input type="number" name="lane_to" class="form-control @error('lane_to') is-invalid @enderror"
             value="{{ old('lane_to') }}">
      @error('lane_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">1BOX人数</label>
      <input type="number" name="box_player_count" class="form-control @error('box_player_count') is-invalid @enderror"
             value="{{ old('box_player_count') }}">
      @error('box_player_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">奇数レーン人数</label>
      <input type="number" name="odd_lane_player_count" class="form-control @error('odd_lane_player_count') is-invalid @enderror"
             value="{{ old('odd_lane_player_count') }}">
      @error('odd_lane_player_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">偶数レーン人数</label>
      <input type="number" name="even_lane_player_count" class="form-control @error('even_lane_player_count') is-invalid @enderror"
             value="{{ old('even_lane_player_count') }}">
      @error('even_lane_player_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">レーン抽選開始</label>
      <input type="datetime-local" name="lane_draw_open_at"
             class="form-control @error('lane_draw_open_at') is-invalid @enderror"
             value="{{ old('lane_draw_open_at') }}">
      @error('lane_draw_open_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">レーン抽選終了</label>
      <input type="datetime-local" name="lane_draw_close_at"
             class="form-control @error('lane_draw_close_at') is-invalid @enderror"
             value="{{ old('lane_draw_close_at') }}">
      @error('lane_draw_close_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
      <div class="small text-muted">
        BOX運用では「奇数レーン人数 + 偶数レーン人数 = 1BOX人数」にしてください。<br>
        例：5番レーン2名、6番レーン3名、BOX5名
      </div>
    </div>
  </div>

  {{-- 会場情報 --}}
  <div class="d-flex align-items-center justify-content-between mt-4">
    <h4 class="mb-0" data-bs-toggle="collapse" href="#t-venue" role="button" aria-expanded="true" aria-controls="t-venue">
      会場情報 <small class="text-muted">（クリックで開閉）</small>
    </h4>
    <a href="{{ route('venues.index') }}" class="btn btn-sm btn-outline-primary">会場を登録・一覧</a>
  </div>

  <div class="form-section row collapse show" id="t-venue">
    <input type="hidden" name="venue_id" id="venue_id" value="{{ old('venue_id') }}">
    <div class="col-md-8 mb-2">
      <label class="form-label">会場検索</label>
      <div class="input-group">
        <input type="text" id="venue_search" class="form-control" placeholder="会場名で検索（3文字以上）">
        <button class="btn btn-outline-secondary" type="button" id="venue_search_btn">検索</button>
      </div>
      <div id="venue_result" class="border rounded mt-1 p-2" style="display:none; max-height:200px; overflow:auto;"></div>
      <small class="text-muted">（検索：事前登録した会場マスタから選択。選ぶと下の会場名・住所・TEL/FAX/URLへ反映）</small>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">会場名</label>
      <input type="text" name="venue_name" id="venue_name" class="form-control @error('venue_name') is-invalid @enderror"
             placeholder="例：○○ボウル" value="{{ old('venue_name') }}">
      @error('venue_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">会場住所</label>
      <input type="text" name="venue_address" id="venue_address" class="form-control @error('venue_address') is-invalid @enderror"
             placeholder="例：東京都千代田区..." value="{{ old('venue_address') }}">
      @error('venue_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">電話番号</label>
      <input type="text" name="venue_tel" id="venue_tel" class="form-control @error('venue_tel') is-invalid @enderror"
             placeholder="例：03-1234-5678" value="{{ old('venue_tel') }}">
      @error('venue_tel')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">FAX</label>
      <input type="text" name="venue_fax" id="venue_fax" class="form-control @error('venue_fax') is-invalid @enderror"
             placeholder="例：03-1234-5679" value="{{ old('venue_fax') }}">
      @error('venue_fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">会場サイトURL</label>
      <input type="url" name="venue_website_url" id="venue_url_view" class="form-control"
             placeholder="https://example.com" value="{{ old('venue_website_url') }}" readonly>
      <small class="text-muted">会場検索で選択すると自動表示。送信時はDB保存しません（表示のみ）。</small>
    </div>
  </div>

  {{-- 主催・協賛等 --}}
  <h4 data-bs-toggle="collapse" href="#t-org" role="button" aria-expanded="false" aria-controls="t-org">
    主催・協賛等 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-org">
    <div class="col-12 mb-2 d-flex align-items-center gap-2 flex-wrap">
      <input type="text" id="org_search_kw" class="form-control" placeholder="組織名で検索（3文字以上）" style="max-width:320px;">
      <button type="button" id="org_search_btn" class="btn btn-outline-secondary btn-sm">検索</button>
      <select id="org_target_cat" class="form-select form-select-sm" style="width:auto;">
        <option value="host">主催へ反映</option>
        <option value="special_sponsor">特別協賛へ反映</option>
        <option value="sponsor" selected>協賛へ反映</option>
        <option value="support">後援へ反映</option>
        <option value="cooperation">協力へ反映</option>
      </select>
      <a href="{{ route('organizations.create') }}" target="_blank" class="btn btn-sm btn-outline-primary">組織を新規登録（別タブ）</a>
      <small class="text-muted">（検索：組織マスタ／ヒットをクリックで選択中の区分に反映）</small>
    </div>
    <div class="col-12 mb-3">
      <div id="org_search_result" class="border rounded p-2" style="display:none; max-height:200px; overflow:auto;"></div>
    </div>

    @php
      $orgCats = [
        'host' => '主催',
        'special_sponsor' => '特別協賛',
        'sponsor' => '協賛',
        'support' => '後援',
        'cooperation' => '協力',
      ];
    @endphp

    @foreach($orgCats as $catKey => $catLabel)
      <div class="col-12 mb-3">
        <label class="form-label">{{ $catLabel }}</label>
        <div data-org-list="{{ $catKey }}"></div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-org-add="{{ $catKey }}">追加</button>
        <small class="text-muted ms-2">（名称とURL。URLは任意）</small>
      </div>
    @endforeach

    <div class="col-12 mb-2">
      <label class="form-label">自由見出し</label>
      <div data-org-list="__free__"></div>
      <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-org-add="__free__">追加</button>
      <small class="text-muted ms-2">（例：実行委員会 / 後援（予定） など）</small>
    </div>
  </div>

  {{-- メディア・賞金 --}}
  <h4 data-bs-toggle="collapse" href="#t-media" role="button" aria-expanded="false" aria-controls="t-media">
    メディア・賞金 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-media">
    <div class="col-md-4 mb-3">
      <label class="form-label">TV放映</label>
      <input type="text" name="broadcast" class="form-control" placeholder="例：BS××"
             value="{{ old('broadcast') }}">
      <input type="url" name="broadcast_url" class="form-control mt-1" placeholder="放映サイトURL（任意）"
             value="{{ old('broadcast_url') }}">
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">配信</label>
      <input type="text" name="streaming" class="form-control" placeholder="例：YouTube Live"
             value="{{ old('streaming') }}">
      <input type="url" name="streaming_url" class="form-control mt-1" placeholder="配信ページURL（任意）"
             value="{{ old('streaming_url') }}">
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">賞金</label>
      <textarea name="prize" class="form-control" rows="2" placeholder="例：男子5,200,000円／女子5,200,000円">{{ old('prize') }}</textarea>
    </div>
  </div>

  {{-- 追加情報 --}}
  <h4 data-bs-toggle="collapse" href="#t-etc" role="button" aria-expanded="false" aria-controls="t-etc">
    追加情報 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-etc">
    <div class="col-md-6 mb-3">
      <label class="form-label">出場条件</label>
      <textarea name="entry_conditions" class="form-control" rows="4"
                placeholder="例：プロ会員のみ、アマチュア枠××名など">{{ old('entry_conditions') }}</textarea>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">資料</label>
      <textarea name="materials" class="form-control" rows="4"
                placeholder="要項・要綱のメモ等">{{ old('materials') }}</textarea>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">入場料</label>
      <textarea name="admission_fee" class="form-control" rows="2"
                placeholder="例：一般1,000円／中学生以下無料 など">{{ old('admission_fee') }}</textarea>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">前年大会</label>
      <input type="text" name="previous_event" class="form-control"
             placeholder="例：第○回××オープン（2024）" value="{{ old('previous_event') }}">
      <input type="url" name="previous_event_url" class="form-control mt-1" placeholder="前年大会ページURL（任意）"
             value="{{ old('previous_event_url') }}">
    </div>

    <h5 class="mt-3">画像アップロード</h5>

    <div class="col-md-6 mb-3">
      <label class="form-label">ポスター画像（複数可）</label>
      <input type="file" name="image" class="form-control mb-2" accept="image/*" id="poster-input">
      <input type="file" name="posters[]" class="form-control" accept="image/*" multiple>
      <small class="text-muted">推奨：横長（16:9 〜 4:3） JPG/PNG</small>
      <div class="mt-2">
        <img id="poster-preview" src="" alt="" style="max-width:100%; display:none; border:1px solid #e5e5e5; border-radius:8px;">
      </div>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">トップ画像</label>
      <input type="file" name="hero_image" class="form-control" accept="image/*">
      <small class="text-muted">大会名の直下に表示されるメイン画像（任意）</small>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">タイトル左ロゴ</label>
      <input type="file" name="title_logo" class="form-control" accept="image/*">
      <small class="text-muted">（任意）タイトルの左に小さなロゴとして表示。</small>
    </div>
  </div>

  {{-- PDFアップロード --}}
  <h4 data-bs-toggle="collapse" href="#t-pdf" role="button" aria-expanded="false" aria-controls="t-pdf">
    PDFアップロード <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-pdf">
    <div class="col-md-6 mb-3">
      <label class="form-label">大会要項（一般公開）PDF</label>
      <input type="file" name="outline_public" class="form-control" accept="application/pdf">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">大会要項（選手用）PDF</label>
      <input type="file" name="outline_player" class="form-control" accept="application/pdf">
      <small class="text-muted">（選手用：将来ログイン必須の想定。可視性はDBに保持）</small>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">オイルパターン表（PDF）</label>
      <input type="file" name="oil_pattern" class="form-control" accept="application/pdf">
    </div>
    <div class="col-md-12 mb-3">
      <label class="form-label">その他PDF（追加）</label>
      <input type="file" name="custom_files[]" class="form-control mb-2" accept="application/pdf" multiple>
      <input type="text" name="custom_titles[]" class="form-control" placeholder="1つ目のタイトル（任意：複数時は順番で使用）">
    </div>
  </div>

  {{-- 右サイド：日程・成績 --}}
  <h4 data-bs-toggle="collapse" href="#t-sidebar-schedule" role="button" aria-expanded="false" aria-controls="t-sidebar-schedule">
    右サイド：日程・成績PDF <small class="text-muted">（右側に縦並びで表示）</small>
  </h4>
  <div class="form-section row collapse" id="t-sidebar-schedule">
    <div class="col-12">
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" id="add_schedule">行を追加</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="add_schedule_hr">区切り線を追加</button>
      </div>
      <div id="schedule_rows" class="mt-2"></div>
      <small class="text-muted d-block mt-1">
        日付は自由入力（例：9/10(木)）／URLかPDFのどちらかでOK（両方あればURL優先）／ラベルだけの行も保存可
      </small>
    </div>
  </div>

  {{-- 右サイド：褒章フォト --}}
  <h4 data-bs-toggle="collapse" href="#t-awards" role="button" aria-expanded="false" aria-controls="t-awards">
    右サイド：褒章フォト
  </h4>
  <div class="form-section row collapse" id="t-awards">
    <div class="col-12">
      <div id="award_rows"></div>
      <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add_award">行を追加</button>
      <small class="text-muted d-block mt-1">種別（パーフェクト／800シリーズ／7-10メイド）ごとに自動で分けて表示します。</small>
    </div>
  </div>

  {{-- 終了後：ギャラリー / 簡易速報PDF --}}
  <h4 data-bs-toggle="collapse" href="#t-gallery" role="button" aria-expanded="false" aria-controls="t-gallery">
    終了後：ギャラリー / 簡易速報PDF
  </h4>
  <div class="form-section row collapse" id="t-gallery">
    <div class="col-md-6 mb-3">
      <label class="form-label">写真ギャラリー（複数）</label>
      <input type="file" name="gallery_files[]" class="form-control" accept="image/*" multiple>
      <small class="text-muted">同順のタイトル（任意）を下に列挙（行区切りOK）</small>
      <textarea name="gallery_titles[]" class="form-control mt-2" rows="2" placeholder="例）決勝スナップ&#10;表彰式 …"></textarea>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">簡易速報PDF（複数）</label>
      <input type="file" name="result_pdfs[]" class="form-control" accept="application/pdf" multiple>
      <small class="text-muted">同順のタイトル（任意）を下に列挙（行区切りOK）</small>
      <textarea name="result_titles[]" class="form-control mt-2" rows="2" placeholder="例）男子前半3G成績&#10;女子決勝 …"></textarea>
    </div>
  </div>

  {{-- 終了後：優勝者・トーナメント --}}
  <h4 data-bs-toggle="collapse" href="#t-result-cards" role="button" aria-expanded="false" aria-controls="t-result-cards">
    終了後：優勝者・トーナメント <small class="text-muted">（複数カードを縦に追加）</small>
  </h4>
  <div class="form-section row collapse" id="t-result-cards">
    <div class="col-12">
      <div id="result_card_rows"></div>
      <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add_result_card">カードを追加</button>
      <small class="text-muted d-block mt-1">
        見出し／選手名／使用ボール／補足／写真／トーナメント表PDF／関連URL を任意で入力。
      </small>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">登録</button>
    <a href="{{ url()->previous() ?: route('tournaments.index') }}" class="btn btn-secondary">キャンセル</a>
  </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const orgInit = @json($orgInit);
  const scheduleInit = @json($scheduleInit);
  const awardsInit = @json($awardsInit);
  const resultCardsInit = @json($resultCardsInit);

  const qs = (sel) => document.querySelector(sel);

  const input = document.getElementById('poster-input');
  const preview = document.getElementById('poster-preview');
  if (input) {
    input.addEventListener('change', (e) => {
      const f = e.target.files?.[0];
      if (!f) {
        preview.style.display = 'none';
        preview.src = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = (ev) => {
        preview.src = ev.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(f);
    });
  }

  const venueBox = qs('#venue_result');
  function renderVenue(items) {
    if (!items || !items.length) {
      venueBox.style.display = 'none';
      venueBox.innerHTML = '';
      return;
    }

    venueBox.innerHTML = items.map(v => `
      <div class="py-1 px-2 hover-bg" data-id="${v.id}" style="cursor:pointer;">
        <strong>${v.name}</strong><br>
        <small>${v.address ?? ''}</small>
      </div>
    `).join('');

    venueBox.style.display = 'block';
    venueBox.querySelectorAll('[data-id]').forEach(el => {
      el.addEventListener('click', async () => {
        const id = el.getAttribute('data-id');
        const res = await fetch(`{{ url('/api/venues') }}/${id}`);
        const v = await res.json();
        qs('#venue_id').value = v.id;
        qs('#venue_name').value = v.name ?? '';
        qs('#venue_address').value = v.address ?? '';
        qs('#venue_tel').value = v.tel ?? '';
        qs('#venue_fax').value = v.fax ?? '';
        const u = document.getElementById('venue_url_view');
        if (u) u.value = v.website_url ?? '';
        venueBox.style.display = 'none';
      });
    });
  }

  async function doSearchVenue() {
    const q = qs('#venue_search').value.trim();
    if (q.length < 3) {
      renderVenue([]);
      return;
    }
    const res = await fetch(`{{ url('/api/venues/search') }}?q=${encodeURIComponent(q)}`);
    renderVenue(await res.json());
  }

  qs('#venue_search_btn')?.addEventListener('click', doSearchVenue);
  qs('#venue_search')?.addEventListener('input', () => {
    clearTimeout(window.__venue_timer);
    window.__venue_timer = setTimeout(doSearchVenue, 250);
  });

  const useShift = document.querySelector('input[name="use_shift_draw"]');
  const acceptShift = document.querySelector('input[name="accept_shift_preference"]');
  const laneMode = document.querySelector('select[name="lane_assignment_mode"]');
  const useLane = document.querySelector('input[name="use_lane_draw"]');

  function toggleOperationFields() {
    const shiftEnabled = !!useShift?.checked;
    const laneEnabled = !!useLane?.checked;
    const isBox = (laneMode?.value || 'single_lane') === 'box';

    document.querySelectorAll('input[name="shift_codes"], input[name="shift_draw_open_at"], input[name="shift_draw_close_at"]').forEach((el) => {
      el.disabled = !shiftEnabled;
    });

    if (acceptShift) {
      acceptShift.disabled = !shiftEnabled;
      if (!shiftEnabled) {
        acceptShift.checked = false;
      }
    }

    document.querySelectorAll('input[name="lane_from"], input[name="lane_to"], input[name="lane_draw_open_at"], input[name="lane_draw_close_at"]').forEach((el) => {
      el.disabled = !laneEnabled;
    });

    document.querySelectorAll('input[name="box_player_count"], input[name="odd_lane_player_count"], input[name="even_lane_player_count"]').forEach((el) => {
      el.disabled = !laneEnabled || !isBox;
    });

    if (laneMode) {
      laneMode.disabled = !laneEnabled;
    }
  }

  useShift?.addEventListener('change', toggleOperationFields);
  useLane?.addEventListener('change', toggleOperationFields);
  laneMode?.addEventListener('change', toggleOperationFields);
  toggleOperationFields();

  const cats = ['host', 'special_sponsor', 'sponsor', 'support', 'cooperation', '__free__'];
  let ORG_SEQ = 0;

  function rowTpl(cat, name = '', url = '') {
    const i = ORG_SEQ++;
    return `
      <div class="row g-2 align-items-center mb-1" data-org-row>
        <input type="hidden" name="org[${i}][category]" value="${cat}">
        <div class="col-md-5">
          <input type="text" name="org[${i}][name]" class="form-control" placeholder="名称" value="${name}">
        </div>
        <div class="col-md-6">
          <input type="url" name="org[${i}][url]" class="form-control" placeholder="https://example.com" value="${url}">
        </div>
        <div class="col-md-1 d-grid">
          <button type="button" class="btn btn-outline-danger" data-org-del>&times;</button>
        </div>
      </div>`;
  }

  function addRowTo(cat, name = '', url = '') {
    const con = document.querySelector(`[data-org-list="${cat}"]`);
    if (!con) return;
    con.insertAdjacentHTML('beforeend', rowTpl(cat, name, url));
    con.querySelectorAll('[data-org-del]').forEach(b => b.onclick = () => b.closest('[data-org-row]').remove());
  }

  cats.forEach(cat => {
    const btn = document.querySelector(`[data-org-add="${cat}"]`);
    if (!btn) return;
    btn.addEventListener('click', () => addRowTo(cat));
  });

  const seenOrg = new Set();
  if (Array.isArray(orgInit)) {
    orgInit.forEach(row => {
      const key = ((row.category || '') + '|' + (row.name || '') + '|' + (row.url || '')).toLowerCase();
      if (seenOrg.has(key)) return;
      seenOrg.add(key);
      addRowTo(row.category || 'sponsor', row.name || '', row.url || '');
    });
  }
  if (!document.querySelector('[data-org-row]')) {
    addRowTo('sponsor');
  }

  const orgBox = document.getElementById('org_search_result');
  async function doOrgSearch() {
    const kw = (document.getElementById('org_search_kw').value || '').trim();
    if (kw.length < 3) {
      orgBox.style.display = 'none';
      orgBox.innerHTML = '';
      return;
    }
    const res = await fetch(`{{ route('api.organizations.search') }}?q=${encodeURIComponent(kw)}`);
    const items = await res.json();
    if (!items.length) {
      orgBox.style.display = 'none';
      orgBox.innerHTML = '';
      return;
    }
    orgBox.innerHTML = items.map(v => `
      <div class="py-1 px-2" data-org='${JSON.stringify(v)}' style="cursor:pointer;">
        <strong>${v.name}</strong> <small class="text-muted">${v.url ?? ''}</small>
      </div>`).join('');
    orgBox.style.display = 'block';
    orgBox.querySelectorAll('[data-org]').forEach(el => {
      el.onclick = () => {
        const v = JSON.parse(el.getAttribute('data-org'));
        const target = (document.getElementById('org_target_cat')?.value) || 'sponsor';
        addRowTo(target, v.name || '', v.url || '');
      };
    });
  }
  document.getElementById('org_search_btn')?.addEventListener('click', doOrgSearch);

  const schBox = document.getElementById('schedule_rows');
  let SCH_SEQ = 0;
  function schTpl(row = {}) {
    const i = SCH_SEQ++;
    const d = row.date || '';
    const l = row.label || '';
    const u = row.url || '';
    const isSep = !!row.separator;
    return `
      <div class="row g-2 align-items-end mb-2 border rounded p-2" data-sch-row>
        <div class="col-md-3">
          <label class="form-label">日付</label>
          <input type="text" name="schedule[${i}][date]" class="form-control" value="${d}" placeholder="例：9/10(木)" data-sch-date>
        </div>
        <div class="col-md-5">
          <label class="form-label">表示ラベル</label>
          <input type="text" name="schedule[${i}][label]" class="form-control" value="${l}" placeholder="例：男子予選前半6G" data-sch-label>
        </div>
        <div class="col-md-3">
          <label class="form-label">外部URL（任意）</label>
          <input type="url" name="schedule[${i}][url]" class="form-control" value="${u}" placeholder="https://..." data-sch-url>
        </div>
        <div class="col-md-1 d-grid gap-1">
          <button type="button" class="btn btn-outline-secondary" data-move="up">↑</button>
          <button type="button" class="btn btn-outline-secondary" data-move="down">↓</button>
          <button type="button" class="btn btn-outline-danger" data-del>&times;</button>
        </div>
        <div class="col-md-8">
          <input type="file" name="schedule_files[${i}]" class="form-control mt-1" accept="application/pdf" data-sch-file>
          <div class="form-check mt-1">
            <input class="form-check-input" type="checkbox" id="sep${i}" name="schedule[${i}][separator]" value="1" ${isSep ? 'checked' : ''} data-sch-sep>
            <label class="form-check-label small" for="sep${i}">この行は区切り線として表示</label>
          </div>
        </div>
      </div>`;
  }
  function bindSchRow(row) {
    row.querySelector('[data-del]').onclick = () => row.remove();
    row.querySelectorAll('[data-move]').forEach(b => {
      b.onclick = () => {
        if (b.dataset.move === 'up' && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
        if (b.dataset.move === 'down' && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
      };
    });
    const sep = row.querySelector('[data-sch-sep]');
    const toggle = (checked) => {
      row.querySelectorAll('[data-sch-date],[data-sch-label],[data-sch-url],[data-sch-file]').forEach(el => el.disabled = checked);
    };
    sep.addEventListener('change', () => toggle(sep.checked));
    toggle(sep.checked);
  }
  function addSchRow(row = {}) {
    schBox.insertAdjacentHTML('beforeend', schTpl(row));
    bindSchRow(schBox.lastElementChild);
  }
  if (Array.isArray(scheduleInit) && scheduleInit.length) {
    scheduleInit.forEach(row => addSchRow(row));
  } else {
    addSchRow({});
  }
  document.getElementById('add_schedule')?.addEventListener('click', () => addSchRow({}));
  document.getElementById('add_schedule_hr')?.addEventListener('click', () => addSchRow({ separator: 1 }));

  const awBox = document.getElementById('award_rows');
  let AW_SEQ = 0;
  function awTpl(row = {}) {
    const i = AW_SEQ++;
    const type = row.type || 'perfect';
    return `
      <div class="row g-2 align-items-end mb-2 border rounded p-2" data-aw-row>
        <div class="col-md-3">
          <label class="form-label">種別</label>
          <select name="awards[${i}][type]" class="form-select">
            <option value="perfect" ${type === 'perfect' ? 'selected' : ''}>パーフェクト</option>
            <option value="series800" ${type === 'series800' ? 'selected' : ''}>800シリーズ</option>
            <option value="split710" ${type === 'split710' ? 'selected' : ''}>7-10メイド</option>
          </select>
        </div>
        <div class="col-md-3"><label class="form-label">選手</label><input type="text" name="awards[${i}][player]" class="form-control" value="${row.player || ''}"></div>
        <div class="col-md-2"><label class="form-label">ゲーム</label><input type="text" name="awards[${i}][game]" class="form-control" value="${row.game || ''}"></div>
        <div class="col-md-2"><label class="form-label">レーン</label><input type="text" name="awards[${i}][lane]" class="form-control" value="${row.lane || ''}"></div>
        <div class="col-md-2 d-grid"><button type="button" class="btn btn-outline-danger" data-aw-del>&times;</button></div>
        <div class="col-md-6"><label class="form-label">見出し</label><input type="text" name="awards[${i}][title]" class="form-control" value="${row.title || ''}"></div>
        <div class="col-md-6"><label class="form-label">補足</label><input type="text" name="awards[${i}][note]" class="form-control" value="${row.note || ''}"></div>
        <div class="col-md-6"><label class="form-label">写真</label><input type="file" name="award_files[${i}]" class="form-control" accept="image/*"></div>
      </div>`;
  }
  function addAwRow(row = {}) {
    awBox.insertAdjacentHTML('beforeend', awTpl(row));
    awBox.lastElementChild.querySelector('[data-aw-del]').onclick = (e) => e.target.closest('[data-aw-row]').remove();
  }
  if (Array.isArray(awardsInit) && awardsInit.length) awardsInit.forEach(r => addAwRow(r)); else addAwRow({});
  document.getElementById('add_award')?.addEventListener('click', () => addAwRow({}));

  const rcBox = document.getElementById('result_card_rows');
  let RC_SEQ = 0;
  function rcTpl(row = {}) {
    const i = RC_SEQ++;
    return `
      <div class="border rounded p-3 mb-3" data-rc-row>
        <div class="row g-2 align-items-end">
          <div class="col-md-5"><label class="form-label">見出し</label><input type="text" name="result_cards[${i}][title]" class="form-control" value="${row.title || ''}"></div>
          <div class="col-md-5"><label class="form-label">選手名</label><input type="text" name="result_cards[${i}][player]" class="form-control" value="${row.player || ''}"></div>
          <div class="col-md-2 d-grid"><button type="button" class="btn btn-outline-danger" data-rc-del>&times;</button></div>
          <div class="col-md-6"><label class="form-label">使用ボール</label><textarea name="result_cards[${i}][balls]" class="form-control" rows="2">${row.balls || ''}</textarea></div>
          <div class="col-md-6"><label class="form-label">補足</label><textarea name="result_cards[${i}][note]" class="form-control" rows="2">${row.note || ''}</textarea></div>
          <div class="col-md-6"><label class="form-label">関連URL</label><input type="url" name="result_cards[${i}][url]" class="form-control" value="${row.url || ''}"></div>
          <div class="col-md-6"><label class="form-label">写真（複数可）</label><input type="file" name="result_card_photos[${i}][]" class="form-control" accept="image/*" multiple></div>
          <div class="col-md-6"><label class="form-label">トーナメント表PDF</label><input type="file" name="result_card_files[${i}]" class="form-control" accept="application/pdf"></div>
        </div>
      </div>`;
  }
  function addRcRow(row = {}) {
    rcBox.insertAdjacentHTML('beforeend', rcTpl(row));
    rcBox.lastElementChild.querySelector('[data-rc-del]').onclick = (e) => e.target.closest('[data-rc-row]').remove();
  }
  if (Array.isArray(resultCardsInit) && resultCardsInit.length) resultCardsInit.forEach(r => addRcRow(r)); else addRcRow({});
  document.getElementById('add_result_card')?.addEventListener('click', () => addRcRow({}));
});
</script>
@endpush
