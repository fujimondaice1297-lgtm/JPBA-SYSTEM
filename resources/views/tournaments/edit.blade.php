{{-- resources/views/tournaments/edit.blade.php --}}
@extends('layouts.app')

@section('content')
@php
  $orgInit = old('org');
  if (!is_array($orgInit) || empty($orgInit)) {
      $orgInit = $tournament->organizations
          ->sortBy('sort_order')
          ->map(fn($o) => [
              'category' => $o->category,
              'name' => $o->name,
              'url' => $o->url,
              'sort_order' => $o->sort_order,
          ])->values()->all();
  }

  $scheduleInit = old('schedule');
  $scheduleKeepInit = old('schedule_keep');
  if (!is_array($scheduleInit) || empty($scheduleInit)) {
      $scheduleInit = collect($tournament->sidebar_schedule ?? [])->map(function($row){
          return [
              'date' => (string)($row['date'] ?? ''),
              'label' => (string)($row['label'] ?? ($row['title'] ?? '')),
              'url' => '',
              'separator' => !empty($row['separator']) ? 1 : 0,
          ];
      })->values()->all();

      $scheduleKeepInit = collect($tournament->sidebar_schedule ?? [])->map(function($row){
          return [
              'keep' => !empty($row['href']) ? 1 : 0,
              'href' => (string)($row['href'] ?? ''),
          ];
      })->values()->all();
  }

  $awardsInit = old('awards');
  $awardsKeepInit = old('awards_keep');
  if (!is_array($awardsInit) || empty($awardsInit)) {
      $awardsInit = collect($tournament->award_highlights ?? [])->map(function($row){
          return [
              'type' => (string)($row['type'] ?? ($row['category'] ?? 'perfect')),
              'player' => (string)($row['player'] ?? ''),
              'game' => (string)($row['game'] ?? ''),
              'lane' => (string)($row['lane'] ?? ''),
              'note' => (string)($row['note'] ?? ''),
              'title' => (string)($row['title'] ?? ''),
          ];
      })->values()->all();

      $awardsKeepInit = collect($tournament->award_highlights ?? [])->map(function($row){
          return [
              'photo' => (string)($row['photo'] ?? ''),
          ];
      })->values()->all();
  }

  $resultCardsInit = old('result_cards');
  $resultCardKeepInit = old('result_card_keep');
  if (!is_array($resultCardsInit) || empty($resultCardsInit)) {
      $resultCardsInit = collect($tournament->result_cards ?? [])->map(function($row){
          return [
              'title' => (string)($row['title'] ?? ''),
              'player' => (string)($row['player'] ?? ''),
              'balls' => (string)($row['balls'] ?? ''),
              'note' => (string)($row['note'] ?? ''),
              'url' => (string)($row['url'] ?? ''),
              'photo' => (string)($row['photo'] ?? ''),
              'photos' => is_array($row['photos'] ?? null) ? $row['photos'] : [],
              'file' => (string)($row['file'] ?? ''),
          ];
      })->values()->all();

      $resultCardKeepInit = collect($tournament->result_cards ?? [])->map(function($row){
          return [
              'photo' => (string)($row['photo'] ?? ''),
              'photos' => is_array($row['photos'] ?? null) ? $row['photos'] : [],
              'file' => (string)($row['file'] ?? ''),
          ];
      })->values()->all();
  }

  $filesByType = $tournament->files->sortBy('sort_order')->groupBy('type');
  $outlinePublic = optional($filesByType->get('outline_public'))->first();
  $outlinePlayer = optional($filesByType->get('outline_player'))->first();
  $oilPattern = optional($filesByType->get('oil_pattern'))->first();
  $customFiles = $filesByType->get('custom', collect())->values();

  $existingGallery = old('__keep_gallery');
  if (!is_array($existingGallery)) {
      $existingGallery = collect($tournament->gallery_items ?? [])->map(function($row){
          return [
              'photo' => $row['photo'] ?? '',
              'title' => $row['title'] ?? '',
          ];
      })->values()->all();
  }

  $existingResults = old('__keep_results');
  if (!is_array($existingResults)) {
      $existingResults = collect($tournament->simple_result_pdfs ?? [])->map(function($row){
          return [
              'file' => $row['file'] ?? '',
              'title' => $row['title'] ?? '',
          ];
      })->values()->all();
  }
@endphp

<h2>大会を編集</h2>

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

<form method="POST" action="{{ route('tournaments.update', $tournament->id) }}" enctype="multipart/form-data" id="tournament-edit-form">
  @csrf
  @method('PUT')

  {{-- 基本情報 --}}
  <h4 data-bs-toggle="collapse" href="#t-basic" role="button" aria-expanded="true" aria-controls="t-basic">
    基本情報 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse show" id="t-basic">
    <div class="col-md-8 mb-3">
      <label class="form-label">大会名 <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
             placeholder="例：全日本選手権" value="{{ old('name', $tournament->name) }}" required>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">大会区分 <span class="text-danger">*</span></label>
      @php $official = old('official_type', $tournament->official_type ?? 'official'); @endphp
      <select name="official_type" class="form-select @error('official_type') is-invalid @enderror" required>
        <option value="official" {{ $official==='official' ? 'selected' : '' }}>公認</option>
        <option value="approved" {{ $official==='approved' ? 'selected' : '' }}>承認</option>
        <option value="other" {{ $official==='other' ? 'selected' : '' }}>その他</option>
      </select>
      @error('official_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">種別（男女） <span class="text-danger">*</span></label>
      @php $g = old('gender', $tournament->gender ?? 'X'); @endphp
      <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
        <option value="M" {{ $g==='M' ? 'selected' : '' }}>男子</option>
        <option value="F" {{ $g==='F' ? 'selected' : '' }}>女子</option>
        <option value="X" {{ $g==='X' ? 'selected' : '' }}>男女/未設定</option>
      </select>
      @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">タイトル区分</label>
      @php $tc = old('title_category', $tournament->title_category ?? 'normal'); @endphp
      <select name="title_category" class="form-select @error('title_category') is-invalid @enderror">
        <option value="normal" {{ $tc==='normal' ? 'selected' : '' }}>通常</option>
        <option value="season_trial" {{ $tc==='season_trial' ? 'selected' : '' }}>シーズントライアル</option>
        <option value="excluded" {{ $tc==='excluded' ? 'selected' : '' }}>除外</option>
      </select>
      @error('title_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted">（タイトル区分：成績反映ボタンの対象分類）</small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">開始日</label>
      <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
             value="{{ old('start_date', optional($tournament->start_date)->format('Y-m-d')) }}">
      @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">終了日</label>
      <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
             value="{{ old('end_date', optional($tournament->end_date)->format('Y-m-d')) }}">
      @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">申込開始日</label>
      <input type="date" name="entry_start" class="form-control @error('entry_start') is-invalid @enderror"
             value="{{ old('entry_start', optional($tournament->entry_start)->format('Y-m-d')) }}">
      @error('entry_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">申込締切日</label>
      <input type="date" name="entry_end" class="form-control @error('entry_end') is-invalid @enderror"
             value="{{ old('entry_end', optional($tournament->entry_end)->format('Y-m-d')) }}">
      @error('entry_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">大会観覧</label>
      @php $sp = old('spectator_policy', $tournament->spectator_policy ?? ''); @endphp
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
      <input type="checkbox" name="inspection_required" value="1"
             class="form-check-input"
             {{ old('inspection_required', $tournament->inspection_required) ? 'checked' : '' }}>
      <small class="text-muted d-block">※ チェック時：検量証未入力のボールは仮登録扱い</small>
    </div>
  </div>

  {{-- 運営 / 抽選設定 --}}
  <h4 data-bs-toggle="collapse" href="#t-operation" role="button" aria-expanded="false" aria-controls="t-operation">
    運営 / 抽選設定 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-operation">

    <div class="col-md-4 mb-3">
      <label class="form-label">予選後フロー</label>
      @php $flowType = old('result_flow_type', $tournament->result_flow_type ?? 'legacy_standard'); @endphp
      <select name="result_flow_type" class="form-select @error('result_flow_type') is-invalid @enderror">
        <option value="legacy_standard" {{ $flowType === 'legacy_standard' ? 'selected' : '' }}>既存（予選→準々決勝→準決勝→決勝）</option>
        <option value="prelim_to_rr_to_final" {{ $flowType === 'prelim_to_rr_to_final' ? 'selected' : '' }}>予選→ラウンドロビン→決勝ステップラダー</option>
        <option value="prelim_to_quarterfinal_to_rr_to_final" {{ $flowType === 'prelim_to_quarterfinal_to_rr_to_final' ? 'selected' : '' }}>予選→準々決勝→ラウンドロビン→決勝ステップラダー</option>
        <option value="prelim_to_single_elimination_to_final" {{ $flowType === 'prelim_to_single_elimination_to_final' ? 'selected' : '' }}>予選→トーナメント→最終成績</option>
        <option value="prelim_to_quarterfinal_to_single_elimination_to_final" {{ $flowType === 'prelim_to_quarterfinal_to_single_elimination_to_final' ? 'selected' : '' }}>予選→準々決勝→トーナメント→最終成績</option>
        <option value="prelim_to_semifinal_to_single_elimination_to_final" {{ $flowType === 'prelim_to_semifinal_to_single_elimination_to_final' ? 'selected' : '' }}>予選→準決勝通算→トーナメント→最終成績</option>
        <option value="prelim_to_shootout_to_final" {{ $flowType === 'prelim_to_shootout_to_final' ? 'selected' : '' }}>予選→シュートアウト→最終成績</option>
        <option value="prelim_to_quarterfinal_to_shootout_to_final" {{ $flowType === 'prelim_to_quarterfinal_to_shootout_to_final' ? 'selected' : '' }}>予選→準々決勝→シュートアウト→最終成績</option>
        <option value="prelim_to_semifinal_to_shootout_to_final" {{ $flowType === 'prelim_to_semifinal_to_shootout_to_final' ? 'selected' : '' }}>予選→準決勝通算→シュートアウト→最終成績</option>     </select>
      @error('result_flow_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted d-block">ラウンドロビンを使う大会だけ、下の進出人数 / ボーナス設定を使います。決勝は現時点ではステップラダー表記です。</small>
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">RR進出人数</label>
      <input type="number" name="round_robin_qualifier_count" class="form-control @error('round_robin_qualifier_count') is-invalid @enderror"
             value="{{ old('round_robin_qualifier_count', $tournament->round_robin_qualifier_count ?? 8) }}">
      @error('round_robin_qualifier_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">勝ちボーナス</label>
      <input type="number" name="round_robin_win_bonus" class="form-control @error('round_robin_win_bonus') is-invalid @enderror"
             value="{{ old('round_robin_win_bonus', $tournament->round_robin_win_bonus ?? 30) }}">
      @error('round_robin_win_bonus')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">引き分けボーナス</label>
      <input type="number" name="round_robin_tie_bonus" class="form-control @error('round_robin_tie_bonus') is-invalid @enderror"
             value="{{ old('round_robin_tie_bonus', $tournament->round_robin_tie_bonus ?? 15) }}">
      @error('round_robin_tie_bonus')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label d-block">ポジションマッチ</label>
      <input type="hidden" name="round_robin_position_round_enabled" value="0">
      <input type="checkbox" name="round_robin_position_round_enabled" value="1" class="form-check-input"
             {{ old('round_robin_position_round_enabled', $tournament->round_robin_position_round_enabled ?? true) ? 'checked' : '' }}>
      <small class="text-muted d-block">総当たり後に順位別1Gを行う</small>
    </div>

    <div class="col-md-12">
      <hr>
      <div class="alert alert-light border mb-3">
        <strong>トーナメント方式設定</strong>
        <div class="small text-muted">
          「予選→トーナメント」「準々決勝→トーナメント」「準決勝通算→トーナメント」を選んだ大会で使用します。
          敗者ラウンドは作らず、同じラウンドで負けた選手は同順位タイとして扱います。
        </div>
      </div>
    </div>

    <div class="col-12 mt-3">
      <div class="border rounded p-3 bg-light">
        <strong>シュートアウト方式設定</strong>
        <div class="small text-muted mt-1">
          「予選→シュートアウト」「準々決勝→シュートアウト」「準決勝通算→シュートアウト」を選んだ大会で使用します。<br>
          標準8名方式では、5〜8位の1st、2〜4位＋1st勝者の2nd、1位通過者＋2nd勝者の優勝決定戦を行います。<br>
          敗退者順位は各マッチのスコア順ではなく、進出元snapshotの通過順位を引き継ぎます。
        </div>
      </div>
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">SO進出人数</label>
      <input type="number" name="shootout_qualifier_count" class="form-control @error('shootout_qualifier_count') is-invalid @enderror"
             value="{{ old('shootout_qualifier_count', $tournament->shootout_qualifier_count ?? 8) }}">
      @error('shootout_qualifier_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted d-block">標準は8名</small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">SO進出元成績</label>
      @php $shootoutSeedSource = old('shootout_seed_source_result_code', $tournament->shootout_seed_source_result_code ?? 'semifinal_total'); @endphp
      <select name="shootout_seed_source_result_code" class="form-select @error('shootout_seed_source_result_code') is-invalid @enderror">
        <option value="prelim_total" {{ $shootoutSeedSource === 'prelim_total' ? 'selected' : '' }}>予選通算成績</option>
        <option value="quarterfinal_total" {{ $shootoutSeedSource === 'quarterfinal_total' ? 'selected' : '' }}>準々決勝通算成績</option>
        <option value="semifinal_total" {{ $shootoutSeedSource === 'semifinal_total' ? 'selected' : '' }}>準決勝通算成績</option>
      </select>
      @error('shootout_seed_source_result_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted d-block">シーズントライアル型は準決勝通算成績を使用</small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">SO方式</label>
      @php $shootoutFormat = old('shootout_format', $tournament->shootout_format ?? 'standard_8'); @endphp
      <select name="shootout_format" class="form-select @error('shootout_format') is-invalid @enderror">
        <option value="standard_8" {{ $shootoutFormat === 'standard_8' ? 'selected' : '' }}>標準8名方式</option>
        <option value="custom" {{ $shootoutFormat === 'custom' ? 'selected' : '' }}>カスタム</option>
      </select>
      @error('shootout_format')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">SO詳細設定（JSON任意）</label>
      @php
        $shootoutSettingsValue = old('shootout_settings');
        if ($shootoutSettingsValue === null) {
            $rawShootoutSettings = $tournament->shootout_settings ?? null;
            $shootoutSettingsValue = is_array($rawShootoutSettings)
                ? json_encode($rawShootoutSettings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : '';
        }
      @endphp
      <textarea name="shootout_settings" rows="3" class="form-control @error('shootout_settings') is-invalid @enderror"
                placeholder='例: {"ranking_policy":"carry_seed_order_for_losers"}'>{{ $shootoutSettingsValue }}</textarea>
      @error('shootout_settings')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted d-block">
        空欄なら標準設定。敗退者順位は通過順位引き継ぎ。
      </small>
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">進出人数</label>
      <input type="number" name="single_elimination_qualifier_count" class="form-control @error('single_elimination_qualifier_count') is-invalid @enderror"
             value="{{ old('single_elimination_qualifier_count', $tournament->single_elimination_qualifier_count ?? 8) }}" min="2" max="64">
      @error('single_elimination_qualifier_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted d-block">例：8 / 16 / 24 / 32</small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">進出元成績</label>
      @php
        $currentSeedSourceLabel = match ($tournament->single_elimination_seed_source_result_code ?? null) {
            'quarterfinal_total' => '準々決勝通算成績',
            'semifinal_total' => '準決勝通算成績',
            default => '予選通算成績',
        };
      @endphp
      <input type="text" class="form-control" value="{{ $currentSeedSourceLabel }}（保存時に予選後フローから自動決定）" readonly>
      <small class="text-muted d-block">
        予選→トーナメントなら予選通算、準々決勝→トーナメントなら準々決勝通算、準決勝通算→トーナメントなら準決勝通算を使います。
      </small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">シード設定方式</label>
      @php $singleSeedPolicy = old('single_elimination_seed_policy', $tournament->single_elimination_seed_policy ?? 'standard'); @endphp
      <select name="single_elimination_seed_policy" class="form-select @error('single_elimination_seed_policy') is-invalid @enderror">
        <option value="standard" {{ $singleSeedPolicy === 'standard' ? 'selected' : '' }}>標準配置</option>
        <option value="higher_seed_bye" {{ $singleSeedPolicy === 'higher_seed_bye' ? 'selected' : '' }}>上位シードへBYE優先</option>
        <option value="custom" {{ $singleSeedPolicy === 'custom' ? 'selected' : '' }}>JSONで個別指定</option>
      </select>
      @error('single_elimination_seed_policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">シード詳細設定（JSON任意）</label>
      @php
        $singleSeedSettings = old('single_elimination_seed_settings', $tournament->single_elimination_seed_settings ?? '');
        if (is_array($singleSeedSettings)) {
            $singleSeedSettings = json_encode($singleSeedSettings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
      @endphp
      <textarea name="single_elimination_seed_settings" rows="4" class="form-control @error('single_elimination_seed_settings') is-invalid @enderror"
                placeholder='例: {"seed_overrides":[{"seed":1,"entry_round":2},{"seed":2,"entry_round":2}] }'>{{ $singleSeedSettings }}</textarea>
      @error('single_elimination_seed_settings')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted d-block">空欄なら標準配置。1回戦シードは entry_round=2、2回戦シードは entry_round=3。</small>
    </div>

    <div class="col-md-12">
      <hr>
      <div class="alert alert-light border mb-3">
        <strong>成績の持ち込み設定</strong>
        <div class="small text-muted">
          通算成績を作るときに、前ステージのスコアを持ち込むか、どこでリセットするかを大会ごとに設定します。
          通常はプルダウンを選ぶだけで大丈夫です。JSON欄は上級者向けです。
        </div>
      </div>
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">持ち込みプリセット</label>
      @php $carryPreset = old('result_carry_preset', $tournament->result_carry_preset ?? 'default'); @endphp
      <select name="result_carry_preset" id="result_carry_preset" class="form-select @error('result_carry_preset') is-invalid @enderror">
        <option value="default" {{ $carryPreset === 'default' ? 'selected' : '' }}>標準（現行どおり）</option>
        <option value="no_carry" {{ $carryPreset === 'no_carry' ? 'selected' : '' }}>全ステージ持ち込みなし</option>
        <option value="reset_after_quarterfinal" {{ $carryPreset === 'reset_after_quarterfinal' ? 'selected' : '' }}>予選→準々決勝までは持ち込み、準決勝からリセット</option>
        <option value="reset_from_quarterfinal" {{ $carryPreset === 'reset_from_quarterfinal' ? 'selected' : '' }}>予選から準々決勝へは持ち込まない</option>
        <option value="carry_to_semifinal_reset_rr" {{ $carryPreset === 'carry_to_semifinal_reset_rr' ? 'selected' : '' }}>予選→準々決勝→準決勝までは持ち込み、ラウンドロビンからリセット</option>
        <option value="carry_prelim_to_semifinal_for_tournament" {{ $carryPreset === 'carry_prelim_to_semifinal_for_tournament' ? 'selected' : '' }}>予選＋準決勝の通算でトーナメント進出者を決定</option>
        <option value="custom" {{ $carryPreset === 'custom' ? 'selected' : '' }}>カスタムJSON</option>
      </select>
      @error('result_carry_preset')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8 mb-3">
      <label class="form-label">持ち込み詳細設定（JSON / 上級者向け）</label>
      @php
        $carrySettings = old('result_carry_settings', $tournament->result_carry_settings ?? '');
        if (is_array($carrySettings)) {
            $carrySettings = json_encode($carrySettings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
      @endphp
      <textarea name="result_carry_settings" id="result_carry_settings" rows="6" class="form-control @error('result_carry_settings') is-invalid @enderror"
                placeholder='例: {"semifinal_total":{"source_stages":["予選","準決勝"]}}'>{{ $carrySettings }}</textarea>
      @error('result_carry_settings')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <small class="text-muted d-block">
        プリセットを選ぶと自動でJSON例が入ります。通常は編集不要です。
      </small>
      <div class="small text-muted mt-2">
        <div>・予選＋準決勝でトーナメント進出者を決める場合：<code>{"semifinal_total":{"source_stages":["予選","準決勝"]}}</code></div>
        <div>・準決勝からリセットする場合：<code>{"semifinal_total":{"source_stages":["準決勝"]}}</code></div>
        <div>・ラウンドロビンから持ち込まない場合：<code>{"round_robin_total":{"source_stages":["ラウンドロビン"]}}</code></div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const preset = document.getElementById('result_carry_preset');
        const textarea = document.getElementById('result_carry_settings');

        if (!preset || !textarea) {
          return;
        }

        const examples = {
          default: {},
          no_carry: {
            quarterfinal_total: { source_stages: ['準々決勝'] },
            semifinal_total: { source_stages: ['準決勝'] },
            round_robin_total: { source_stages: ['ラウンドロビン'] },
            final_total: { source_stages: ['決勝'] }
          },
          reset_after_quarterfinal: {
            quarterfinal_total: { source_stages: ['予選', '準々決勝'] },
            semifinal_total: { source_stages: ['準決勝'] },
            round_robin_total: { source_stages: ['準決勝', 'ラウンドロビン'] },
            final_total: { source_stages: ['準決勝', '決勝'] }
          },
          reset_from_quarterfinal: {
            quarterfinal_total: { source_stages: ['準々決勝'] },
            semifinal_total: { source_stages: ['準々決勝', '準決勝'] },
            round_robin_total: { source_stages: ['準々決勝', '準決勝', 'ラウンドロビン'] },
            final_total: { source_stages: ['準々決勝', '準決勝', '決勝'] }
          },
          carry_to_semifinal_reset_rr: {
            quarterfinal_total: { source_stages: ['予選', '準々決勝'] },
            semifinal_total: { source_stages: ['予選', '準々決勝', '準決勝'] },
            round_robin_total: { source_stages: ['ラウンドロビン'] }
          },
          carry_prelim_to_semifinal_for_tournament: {
            semifinal_total: { source_stages: ['予選', '準決勝'] }
          }
        };

        preset.addEventListener('change', function () {
          if (preset.value === 'custom') {
            return;
          }

          textarea.value = JSON.stringify(examples[preset.value] || {}, null, 2);
        });
      });
    </script>

    <div class="col-md-3 mb-3">
      <label class="form-label d-block">シフト抽選を使う</label>
      <input type="hidden" name="use_shift_draw" value="0">
      <input type="checkbox" name="use_shift_draw" value="1" class="form-check-input"
             {{ old('use_shift_draw', $tournament->use_shift_draw) ? 'checked' : '' }}>
    </div>

    <div class="col-md-5 mb-3">
      <label class="form-label">シフト候補</label>
      <input type="text" name="shift_codes" class="form-control @error('shift_codes') is-invalid @enderror"
             placeholder="例：A,B,C"
             value="{{ old('shift_codes', $tournament->shift_codes) }}">
      @error('shift_codes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label d-block">希望シフト受付</label>
      <input type="hidden" name="accept_shift_preference" value="0">
      <input type="checkbox" name="accept_shift_preference" value="1" class="form-check-input"
             {{ old('accept_shift_preference', $tournament->accept_shift_preference) ? 'checked' : '' }}>
      <small class="text-muted d-block">会員エントリー時に希望シフトを受け付けます。</small>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">シフト抽選開始</label>
      <input type="datetime-local" name="shift_draw_open_at"
             class="form-control @error('shift_draw_open_at') is-invalid @enderror"
             value="{{ old('shift_draw_open_at', optional($tournament->shift_draw_open_at)->format('Y-m-d\TH:i')) }}">
      @error('shift_draw_open_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">シフト抽選終了</label>
      <input type="datetime-local" name="shift_draw_close_at"
             class="form-control @error('shift_draw_close_at') is-invalid @enderror"
             value="{{ old('shift_draw_close_at', optional($tournament->shift_draw_close_at)->format('Y-m-d\TH:i')) }}">
      @error('shift_draw_close_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label d-block">レーン抽選を使う</label>
      <input type="hidden" name="use_lane_draw" value="0">
      <input type="checkbox" name="use_lane_draw" value="1" class="form-check-input"
             {{ old('use_lane_draw', $tournament->use_lane_draw) ? 'checked' : '' }}>
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">割付方式</label>
      @php $laneMode = old('lane_assignment_mode', $tournament->lane_assignment_mode ?? 'single_lane'); @endphp
      <select name="lane_assignment_mode" class="form-select @error('lane_assignment_mode') is-invalid @enderror">
        <option value="single_lane" {{ $laneMode === 'single_lane' ? 'selected' : '' }}>通常レーン割付</option>
        <option value="box" {{ $laneMode === 'box' ? 'selected' : '' }}>BOX運用</option>
      </select>
      @error('lane_assignment_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">使用レーン開始</label>
      <input type="number" name="lane_from"
             class="form-control @error('lane_from') is-invalid @enderror"
             value="{{ old('lane_from', $tournament->lane_from) }}">
      @error('lane_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">使用レーン終了</label>
      <input type="number" name="lane_to"
             class="form-control @error('lane_to') is-invalid @enderror"
             value="{{ old('lane_to', $tournament->lane_to) }}">
      @error('lane_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">1BOX人数</label>
      <input type="number" name="box_player_count"
             class="form-control @error('box_player_count') is-invalid @enderror"
             value="{{ old('box_player_count', $tournament->box_player_count) }}">
      @error('box_player_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">奇数レーン人数</label>
      <input type="number" name="odd_lane_player_count"
             class="form-control @error('odd_lane_player_count') is-invalid @enderror"
             value="{{ old('odd_lane_player_count', $tournament->odd_lane_player_count) }}">
      @error('odd_lane_player_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2 mb-3">
      <label class="form-label">偶数レーン人数</label>
      <input type="number" name="even_lane_player_count"
             class="form-control @error('even_lane_player_count') is-invalid @enderror"
             value="{{ old('even_lane_player_count', $tournament->even_lane_player_count) }}">
      @error('even_lane_player_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">レーン抽選開始</label>
      <input type="datetime-local" name="lane_draw_open_at"
             class="form-control @error('lane_draw_open_at') is-invalid @enderror"
             value="{{ old('lane_draw_open_at', optional($tournament->lane_draw_open_at)->format('Y-m-d\TH:i')) }}">
      @error('lane_draw_open_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">レーン抽選終了</label>
      <input type="datetime-local" name="lane_draw_close_at"
             class="form-control @error('lane_draw_close_at') is-invalid @enderror"
             value="{{ old('lane_draw_close_at', optional($tournament->lane_draw_close_at)->format('Y-m-d\TH:i')) }}">
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
    <input type="hidden" name="venue_id" id="venue_id" value="{{ old('venue_id', $tournament->venue_id) }}">

    <div class="col-md-8 mb-2">
      <label class="form-label">会場検索</label>
      <div class="input-group">
        <input type="text" id="venue_search" class="form-control" placeholder="会場名で検索（3文字以上）">
        <button class="btn btn-outline-secondary" type="button" id="venue_search_btn">検索</button>
      </div>
      <div id="venue_result" class="border rounded mt-1 p-2" style="display:none; max-height:200px; overflow:auto;"></div>
      <small class="text-muted">（検索：会場マスタから選択。選ぶと下の会場名・住所・TEL/FAX/URLへ反映）</small>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">会場名</label>
      <input type="text" name="venue_name" id="venue_name" class="form-control @error('venue_name') is-invalid @enderror"
             placeholder="例：○○ボウル" value="{{ old('venue_name', $tournament->venue_name) }}">
      @error('venue_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">会場住所</label>
      <input type="text" name="venue_address" id="venue_address" class="form-control @error('venue_address') is-invalid @enderror"
             placeholder="例：東京都千代田区..." value="{{ old('venue_address', $tournament->venue_address) }}">
      @error('venue_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">電話番号</label>
      <input type="text" name="venue_tel" id="venue_tel" class="form-control @error('venue_tel') is-invalid @enderror"
             placeholder="例：03-1234-5678" value="{{ old('venue_tel', $tournament->venue_tel) }}">
      @error('venue_tel')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">FAX</label>
      <input type="text" name="venue_fax" id="venue_fax" class="form-control @error('venue_fax') is-invalid @enderror"
             placeholder="例：03-1234-5679" value="{{ old('venue_fax', $tournament->venue_fax) }}">
      @error('venue_fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">会場サイトURL</label>
      <input type="url" name="venue_website_url" id="venue_url_view" class="form-control"
             placeholder="https://example.com"
             value="{{ old('venue_website_url', $tournament->venue?->website_url) }}" readonly>
      <small class="text-muted">会場検索で自動表示（保存対象外）。</small>
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
             value="{{ old('broadcast', $tournament->broadcast) }}">
      <input type="url" name="broadcast_url" class="form-control mt-1" placeholder="放映サイトURL（任意）"
             value="{{ old('broadcast_url', $tournament->broadcast_url) }}">
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">配信</label>
      <input type="text" name="streaming" class="form-control" placeholder="例：YouTube Live"
             value="{{ old('streaming', $tournament->streaming) }}">
      <input type="url" name="streaming_url" class="form-control mt-1" placeholder="配信ページURL（任意）"
             value="{{ old('streaming_url', $tournament->streaming_url) }}">
    </div>

    <div class="col-md-4 mb-3">
      <label class="form-label">賞金</label>
      <textarea name="prize" class="form-control" rows="2" placeholder="例：男子5,200,000円／女子5,200,000円">{{ old('prize', $tournament->prize) }}</textarea>
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
                placeholder="例：プロ会員のみ、アマチュア枠××名など">{{ old('entry_conditions', $tournament->entry_conditions) }}</textarea>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">資料</label>
      <textarea name="materials" class="form-control" rows="4"
                placeholder="要項・要綱のメモ等">{{ old('materials', $tournament->materials) }}</textarea>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">入場料</label>
      <textarea name="admission_fee" class="form-control" rows="2"
                placeholder="例：一般1,000円／中学生以下無料 など">{{ old('admission_fee', $tournament->admission_fee) }}</textarea>
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">前年大会</label>
      <input type="text" name="previous_event" class="form-control"
             placeholder="例：第○回××オープン（2024）" value="{{ old('previous_event', $tournament->previous_event) }}">
      <input type="url" name="previous_event_url" class="form-control mt-1" placeholder="前年大会ページURL（任意）"
             value="{{ old('previous_event_url', $tournament->previous_event_url) }}">
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

      @if(!empty($tournament->image_path) || (is_array($tournament->poster_images) && count($tournament->poster_images)))
        <div class="mt-2 border rounded p-2">
          <div class="small mb-1">現在のポスター</div>
          <div class="d-flex flex-wrap gap-2">
            @if(!empty($tournament->image_path))
              <img src="{{ asset('storage/'.$tournament->image_path) }}" style="height:90px; border:1px solid #eee; border-radius:6px;">
            @endif
            @if(is_array($tournament->poster_images))
              @foreach($tournament->poster_images as $posterPath)
                <img src="{{ asset('storage/'.$posterPath) }}" style="height:90px; border:1px solid #eee; border-radius:6px;">
              @endforeach
            @endif
          </div>
          <small class="text-muted d-block mt-1">追加アップロードのみ対応しています。既存画像は自動保持されます。</small>
        </div>
      @endif
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">トップ画像</label>
      <input type="file" name="hero_image" class="form-control" accept="image/*">
      <small class="text-muted">大会名の直下に表示されるメイン画像（任意）</small>
      @if(!empty($tournament->hero_image_path))
        <div class="mt-2">
          <img src="{{ asset('storage/'.$tournament->hero_image_path) }}" style="max-width:100%; max-height:140px; border:1px solid #eee; border-radius:8px;">
        </div>
      @endif
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">タイトル左ロゴ</label>
      <input type="file" name="title_logo" class="form-control" accept="image/*">
      @if(!empty($tournament->title_logo_path))
        <div class="mt-2 d-flex align-items-center gap-2">
          <img src="{{ asset('storage/'.$tournament->title_logo_path) }}" alt="title logo" style="height:40px; width:auto; border:1px solid #eee; border-radius:6px; background:#fff;">
          <small class="text-muted">未選択ならこのロゴを維持します。</small>
        </div>
      @endif
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
      @if($outlinePublic)
        <div class="mt-2">
          <a href="{{ asset('storage/'.$outlinePublic->file_path) }}" target="_blank" rel="noopener">現在のファイルを見る</a>
          <small class="text-muted d-block">新しいPDFを選ぶと差し替えます。</small>
        </div>
      @endif
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">大会要項（選手用）PDF</label>
      <input type="file" name="outline_player" class="form-control" accept="application/pdf">
      @if($outlinePlayer)
        <div class="mt-2">
          <a href="{{ asset('storage/'.$outlinePlayer->file_path) }}" target="_blank" rel="noopener">現在のファイルを見る</a>
          <small class="text-muted d-block">新しいPDFを選ぶと差し替えます。</small>
        </div>
      @endif
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">オイルパターン表（PDF）</label>
      <input type="file" name="oil_pattern" class="form-control" accept="application/pdf">
      @if($oilPattern)
        <div class="mt-2">
          <a href="{{ asset('storage/'.$oilPattern->file_path) }}" target="_blank" rel="noopener">現在のファイルを見る</a>
          <small class="text-muted d-block">新しいPDFを選ぶと差し替えます。</small>
        </div>
      @endif
    </div>

    <div class="col-md-12 mb-3">
      <label class="form-label">その他PDF（追加）</label>
      <input type="file" name="custom_files[]" class="form-control mb-2" accept="application/pdf" multiple>
      <input type="text" name="custom_titles[]" class="form-control" placeholder="1つ目のタイトル（任意：複数時は順番で使用）">
      <small class="text-muted d-block mt-1">その他PDFは追加登録です。既存の custom ファイルは自動保持されます。</small>

      @if($customFiles->count())
        <div class="mt-2 border rounded p-2">
          <div class="small mb-1">現在の追加PDF</div>
          @foreach($customFiles as $customFile)
            <div>
              <a href="{{ asset('storage/'.$customFile->file_path) }}" target="_blank" rel="noopener">
                {{ $customFile->title ?: '資料' }}
              </a>
            </div>
          @endforeach
        </div>
      @endif
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
        日付は自由入力（例：9/10(木)）／URLかPDFのどちらかでOK（両方あればURL優先）／
        <strong>ラベルだけ</strong>の行も保存可（＝リンク無しの見出し用）／
        <strong>区切り線</strong>は同日の項目間に水平線を挿入
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
      <small class="text-muted d-block mt-1">種別（パーフェクト／800シリーズ／7-10メイド）ごとに自動で分けて表示。既存写真はデフォルトで維持されます。</small>
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

      @if(is_array($existingGallery) && count($existingGallery))
        <div class="mt-2 border rounded p-2">
          <div class="mb-1">既存ギャラリー（残す分のみチェック）</div>
          @foreach($existingGallery as $idx => $gi)
            <div class="form-check">
              <input class="form-check-input" type="checkbox"
                     name="__keep_gallery[{{ $idx }}][photo]"
                     value="{{ $gi['photo'] ?? '' }}"
                     id="gk{{ $idx }}"
                     {{ !empty($gi['photo']) ? 'checked' : '' }}>
              <label class="form-check-label" for="gk{{ $idx }}">
                @if(!empty($gi['photo']))
                  <img src="{{ asset('storage/'.$gi['photo']) }}" style="height:40px">
                @endif
                {{ $gi['title'] ?? '' }}
              </label>
              <input type="hidden" name="__keep_gallery[{{ $idx }}][title]" value="{{ $gi['title'] ?? '' }}">
            </div>
          @endforeach
        </div>
      @endif
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">簡易速報PDF（複数）</label>
      <input type="file" name="result_pdfs[]" class="form-control" accept="application/pdf" multiple>
      <small class="text-muted">同順のタイトル（任意）を下に列挙（行区切りOK）</small>
      <textarea name="result_titles[]" class="form-control mt-2" rows="2" placeholder="例）男子前半3G成績&#10;女子決勝 …"></textarea>

      @if(is_array($existingResults) && count($existingResults))
        <div class="mt-2 border rounded p-2">
          <div class="mb-1">既存PDF（残す分のみチェック）</div>
          @foreach($existingResults as $idx => $ri)
            <div class="form-check">
              <input class="form-check-input" type="checkbox"
                     name="__keep_results[{{ $idx }}][file]"
                     value="{{ $ri['file'] ?? '' }}"
                     id="rk{{ $idx }}"
                     {{ !empty($ri['file']) ? 'checked' : '' }}>
              <label class="form-check-label" for="rk{{ $idx }}">
                {{ $ri['title'] ?? '速報' }}（{{ !empty($ri['file']) ? basename($ri['file']) : '' }}）
              </label>
              <input type="hidden" name="__keep_results[{{ $idx }}][title]" value="{{ $ri['title'] ?? '' }}">
            </div>
          @endforeach
        </div>
      @endif
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
        見出し（例：男子レギュラー部門 優勝）／選手名／使用ボール（複数行OK）／補足／写真／トーナメント表PDF／関連URL を任意で入力。
        写真・PDFは未指定なら既存を<strong>自動維持</strong>します。
      </small>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">更新</button>
    <a href="{{ route('tournaments.show', $tournament->id) }}" class="btn btn-secondary">詳細へ戻る</a>
  </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const orgInit = @json($orgInit);
  const scheduleInit = @json($scheduleInit);
  const scheduleKeepInit = @json($scheduleKeepInit);
  const awardsInit = @json($awardsInit);
  const awardsKeepInit = @json($awardsKeepInit);
  const resultCardsInit = @json($resultCardsInit);
  const resultCardKeepInit = @json($resultCardKeepInit);

  // 画像プレビュー
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

  // 会場検索
  const qs = (sel) => document.querySelector(sel);
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
        const res = await fetch('{{ url('/api/venues/__ID__') }}'.replace('__ID__', id));
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

  qs('#venue_search_btn').addEventListener('click', doSearchVenue);
  qs('#venue_search').addEventListener('input', () => {
    clearTimeout(window.__venue_timer);
    window.__venue_timer = setTimeout(doSearchVenue, 250);
  });

  // 運営 / 抽選設定
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

  // 組織
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

  // 組織マスタ検索
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

  document.getElementById('org_search_btn').addEventListener('click', doOrgSearch);

  // サイドバー：日程・成績
  const schBox = document.getElementById('schedule_rows');
  const addSch = document.getElementById('add_schedule');
  const addSchHr = document.getElementById('add_schedule_hr');
  let SCH_SEQ = 0;

  function schTpl(row = {}, keep = {}) {
    const i = SCH_SEQ++;
    const d = row.date || '';
    const l = row.label || '';
    const u = row.url || '';
    const isSep = !!row.separator;
    const keepChecked = !!keep.keep;
    const existingHref = keep.href || '';

    const keepHtml = existingHref ? `
      <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" id="sk${i}" name="schedule_keep[${i}][keep]" value="1" ${keepChecked ? 'checked' : ''}>
        <label class="form-check-label small" for="sk${i}">既存リンクを維持（${existingHref.split('/').pop()}）</label>
        <input type="hidden" name="schedule_keep[${i}][href]" value="${existingHref}">
      </div>` : '';

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
          <small class="text-muted">※ URLかPDFのどちらかでOK（両方あればURL優先）／ラベルのみでも保存可</small>
          ${keepHtml}
          <div class="form-check mt-1">
            <input class="form-check-input" type="checkbox" id="sep${i}" name="schedule[${i}][separator]" value="1" ${isSep ? 'checked' : ''} data-sch-sep>
            <label class="form-check-label small" for="sep${i}">この行は <strong>区切り線</strong> として表示（他入力は無視）</label>
          </div>
        </div>
      </div>`;
  }

  function bindSchRow(row) {
    row.querySelector('[data-del]').onclick = () => row.remove();
    row.querySelectorAll('[data-move]').forEach(b => {
      b.onclick = () => {
        if (b.dataset.move === 'up' && row.previousElementSibling) {
          row.parentNode.insertBefore(row, row.previousElementSibling);
        }
        if (b.dataset.move === 'down' && row.nextElementSibling) {
          row.parentNode.insertBefore(row.nextElementSibling, row);
        }
      };
    });

    const sep = row.querySelector('[data-sch-sep]');
    const toggle = (checked) => {
      row.querySelectorAll('[data-sch-date],[data-sch-label],[data-sch-url],[data-sch-file]').forEach(el => {
        el.disabled = checked;
      });
    };
    sep.addEventListener('change', () => toggle(sep.checked));
    toggle(sep.checked);
  }

  function addSchRow(row = {}, keep = {}) {
    schBox.insertAdjacentHTML('beforeend', schTpl(row, keep));
    bindSchRow(schBox.lastElementChild);
  }

  if (Array.isArray(scheduleInit) && scheduleInit.length) {
    scheduleInit.forEach((row, idx) => addSchRow(row, scheduleKeepInit[idx] || {}));
  } else {
    addSchRow({}, {});
  }

  addSch.addEventListener('click', () => addSchRow({}, {}));
  addSchHr.addEventListener('click', () => addSchRow({ separator: 1 }, {}));

  // 褒章
  const awBox = document.getElementById('award_rows');
  const addAw = document.getElementById('add_award');
  let AW_SEQ = 0;

  function awTpl(row = {}, keep = {}) {
    const i = AW_SEQ++;
    const type = row.type || 'perfect';
    const player = row.player || '';
    const game = row.game || '';
    const lane = row.lane || '';
    const note = row.note || '';
    const title = row.title || '';
    const existingPhoto = keep.photo || '';

    const preview = existingPhoto ? `
      <div class="d-flex align-items-center gap-2 mt-1">
        <img src="{{ asset('storage') }}/${existingPhoto}" style="width:92px;height:92px;object-fit:cover;border-radius:6px;border:1px solid #eee;">
        <span class="small text-muted">既存写真は未指定時に維持されます</span>
      </div>
      <input type="hidden" name="awards_keep[${i}][photo]" value="${existingPhoto}">
    ` : '';

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
        <div class="col-md-3">
          <label class="form-label">選手</label>
          <input type="text" name="awards[${i}][player]" class="form-control" value="${player}" placeholder="例：山田 太郎">
        </div>
        <div class="col-md-2">
          <label class="form-label">ゲーム</label>
          <input type="text" name="awards[${i}][game]" class="form-control" value="${game}" placeholder="例：前半3G">
        </div>
        <div class="col-md-2">
          <label class="form-label">レーン</label>
          <input type="text" name="awards[${i}][lane]" class="form-control" value="${lane}" placeholder="例：21-22L">
        </div>
        <div class="col-md-2">
          <label class="form-label">備考</label>
          <input type="text" name="awards[${i}][note]" class="form-control" value="${note}">
        </div>
        <div class="col-md-6">
          <label class="form-label">補助ラベル（任意）</label>
          <input type="text" name="awards[${i}][title]" class="form-control" value="${title}" placeholder="例：大会第1号パーフェクト">
          <input type="file" name="award_files[${i}]" class="form-control mt-1" accept="image/*">
          ${preview}
        </div>
        <div class="col-md-1 d-grid">
          <button type="button" class="btn btn-outline-danger" data-aw-del>&times;</button>
        </div>
      </div>`;
  }

  function addAwRow(row = {}, keep = {}) {
    awBox.insertAdjacentHTML('beforeend', awTpl(row, keep));
    awBox.querySelectorAll('[data-aw-del]').forEach(b => b.onclick = () => b.closest('[data-aw-row]').remove());
  }

  if (Array.isArray(awardsInit) && awardsInit.length) {
    awardsInit.forEach((row, idx) => addAwRow(row, awardsKeepInit[idx] || {}));
  } else {
    addAwRow({}, {});
  }

  addAw.addEventListener('click', () => addAwRow({}, {}));

  // 結果カード
  const rcBox = document.getElementById('result_card_rows');
  const addRc = document.getElementById('add_result_card');
  let RC_SEQ = 0;

  function rcTpl(data = {}, keep = {}) {
    const i = RC_SEQ++;
    const title = data.title || '';
    const player = data.player || '';
    const balls = data.balls || '';
    const note = data.note || '';
    const url = data.url || '';

    const existingPhotos = Array.isArray(keep.photos) && keep.photos.length
      ? keep.photos
      : (keep.photo ? [keep.photo] : []);

    const keepPhotosHtml = existingPhotos.length ? `
      <div class="mt-2 border rounded p-2">
        <div class="mb-1 small">既存写真（残す分のみチェック）</div>
        ${existingPhotos.map((p, idx) => `
          <div class="form-check form-check-inline me-3 mb-1">
            <input class="form-check-input" type="checkbox" name="result_card_keep[${i}][photos][]" id="rcp${i}_${idx}" value="${p}" checked>
            <label class="form-check-label" for="rcp${i}_${idx}">
              <img src="{{ asset('storage') }}/${p}" style="width:100px;height:66px;object-fit:cover;border:1px solid #eee;border-radius:6px;">
            </label>
          </div>
        `).join('')}
        <input type="hidden" name="result_card_keep[${i}][photo]" value="${existingPhotos[0] || ''}">
      </div>
    ` : '';

    const keepFile = keep.file ? `
      <div class="mt-1 small text-muted">既存PDF：${keep.file.split('/').pop()}</div>
      <input type="hidden" name="result_card_keep[${i}][file]" value="${keep.file}">
    ` : '';

    return `
      <div class="border rounded p-2 mb-2" data-rc-row>
        <div class="row g-2 align-items-end">
          <div class="col-md-5">
            <label class="form-label">見出し</label>
            <input type="text" class="form-control" name="result_cards[${i}][title]" value="${title}" placeholder="例：男子レギュラー部門 優勝">
          </div>
          <div class="col-md-3">
            <label class="form-label">選手名</label>
            <input type="text" class="form-control" name="result_cards[${i}][player]" value="${player}" placeholder="例：和田 秀和">
          </div>
          <div class="col-md-4">
            <label class="form-label">関連URL（任意）</label>
            <input type="url" class="form-control" name="result_cards[${i}][url]" value="${url}" placeholder="https://...">
          </div>

          <div class="col-md-6">
            <label class="form-label">使用ボール等（複数行OK）</label>
            <textarea class="form-control" name="result_cards[${i}][balls]" rows="3" placeholder="例：優勝ボール：HARSH REALITY PEARL ほか">${balls}</textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">補足</label>
            <input type="text" class="form-control" name="result_cards[${i}][note]" value="${note}" placeholder="例：男子48期 No.1109">
          </div>

          <div class="col-md-6">
            <label class="form-label">写真（複数可）</label>
            <input type="file" class="form-control" name="result_card_photos[${i}][]" accept="image/*" multiple>
            ${keepPhotosHtml}
          </div>
          <div class="col-md-6">
            <label class="form-label">トーナメント表PDF（任意）</label>
            <input type="file" class="form-control" name="result_card_files[${i}]" accept="application/pdf">
            ${keepFile}
          </div>

          <div class="col-md-2 d-grid mt-2">
            <button type="button" class="btn btn-outline-secondary" data-move="up">↑</button>
          </div>
          <div class="col-md-2 d-grid mt-2">
            <button type="button" class="btn btn-outline-secondary" data-move="down">↓</button>
          </div>
          <div class="col-md-2 d-grid mt-2">
            <button type="button" class="btn btn-outline-danger" data-rc-del>&times; 削除</button>
          </div>
        </div>
      </div>`;
  }

  function bindRcRow(row) {
    row.querySelector('[data-rc-del]').onclick = () => row.remove();
    row.querySelectorAll('[data-move]').forEach(b => {
      b.onclick = () => {
        if (b.dataset.move === 'up' && row.previousElementSibling) {
          row.parentNode.insertBefore(row, row.previousElementSibling);
        }
        if (b.dataset.move === 'down' && row.nextElementSibling) {
          row.parentNode.insertBefore(row.nextElementSibling, row);
        }
      };
    });
  }

  function addRcRow(data = {}, keep = {}) {
    rcBox.insertAdjacentHTML('beforeend', rcTpl(data, keep));
    bindRcRow(rcBox.lastElementChild);
  }

  if (Array.isArray(resultCardsInit) && resultCardsInit.length) {
    resultCardsInit.forEach((row, idx) => addRcRow(row, resultCardKeepInit[idx] || {}));
  } else {
    addRcRow({}, {});
  }

  addRc.addEventListener('click', () => addRcRow({}, {}));
});
</script>
@endpush

@endsection