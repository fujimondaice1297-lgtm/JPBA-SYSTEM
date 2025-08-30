@extends('layouts.app')

@php
  $isAdmin = auth()->check() && optional(auth()->user())->isAdmin();

  // ← 送信先を権限で切り替える（ここがポイント）
  $action = isset($bowler)
      ? ($isAdmin ? route('pro_bowlers.update', $bowler->id) : route('athlete.update', $bowler->id))
      : route('pro_bowlers.store');
@endphp

@section('content')
{{-- debug --}}<div class="text-muted small">
user.pro_bowler_id={{ auth()->user()?->pro_bowler_id }},
editing_id={{ $bowler->id ?? 'new' }}
</div>

<h2>選手データ登録</h2>

{{-- 管理者のみ表示する補助ウィジェット --}}
@includeWhen($isAdmin && isset($bowler) && $bowler?->id, 'pro_bowlers._training_widget', ['bowler' => $bowler])

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

<form id="bowler-update-form" method="POST"
      action="{{ $isAdmin
                  ? (isset($bowler) ? route('pro_bowlers.update', $bowler->id) : route('pro_bowlers.store'))
                  : route('athlete.update', $bowler->id) }}"
      enctype="multipart/form-data">

  @csrf
  @if(isset($bowler)) @method('PUT') @endif

  <input type="hidden" name="pro_bowler_id" value="{{ old('pro_bowler_id', $bowler->pro_bowler_id ?? '') }}">

  {{-- ========== ADMIN-ONLY START ========== --}}
  @if($isAdmin)

    {{-- 公開情報（HPに表示） --}}
    <h4 data-bs-toggle="collapse" href="#public-display-section" role="button" aria-expanded="true" aria-controls="public-display-section">
      公開情報（HPに表示） <small class="text-muted">（クリックで開閉）</small>
    </h4>
    <div class="form-section row collapse show" id="public-display-section">
      <div class="col-md-6 mb-3">
        <label>ライセンスNo<span class="required">＊半角英数</span></label>
        <input type="text" name="license_no" class="form-control @error('license_no') is-invalid @enderror" required placeholder="例：m000123" value="{{ old('license_no', $bowler->license_no ?? '') }}">
        @error('license_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-6 mb-3">
        <label>氏名<span class="required">＊必須</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="例：山田 太郎" value="{{ old('name', $bowler->name_kanji ?? '') }}">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-6 mb-3">
        <label>フリガナ</label>
        <input type="text" name="furigana" class="form-control @error('furigana') is-invalid @enderror" placeholder="例：ヤマダ タロウ" value="{{ old('furigana', $bowler->name_kana ?? '') }}">
        @error('furigana')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-6 mb-3">
        <label>性別</label>
        <select name="gender" class="form-control @error('gender') is-invalid @enderror">
          <option value="">選んでください</option>
          <option value="男性" {{ old('gender', ($bowler->sex ?? '') === 1 ? '男性' : (($bowler->sex ?? '') === 2 ? '女性' : '')) == '男性' ? 'selected' : '' }}>男性</option>
          <option value="女性" {{ old('gender', ($bowler->sex ?? '') === 1 ? '男性' : (($bowler->sex ?? '') === 2 ? '女性' : '')) == '女性' ? 'selected' : '' }}>女性</option>
        </select>
        @error('gender')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3 mb-3">
        <label>期別</label>
        <select name="kibetsu" class="form-control @error('kibetsu') is-invalid @enderror">
          @for($i = 1; $i <= 70; $i++)
            <option value="{{ $i }}" {{ old('kibetsu', $bowler->kibetsu ?? '') == $i ? 'selected' : '' }}>{{ $i }}期</option>
          @endfor
        </select>
        @error('kibetsu')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3 mb-3">
        <label>地区</label>
        <select name="district" class="form-control @error('district') is-invalid @enderror">
          <option value="">選んでください</option>
          @foreach ($districts as $id => $label)
            <option value="{{ $id }}" {{ old('district', $bowler->district_id ?? '') == $id ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        @error('district')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-6 mb-3">
        <label>所属先名</label>
        <input type="text" name="organization_name" class="form-control" value="{{ old('organization_name', $bowler->organization_name ?? '') }}">
      </div>
      <div class="col-md-6 mb-3">
        <label>所属先URL</label>
        <input type="text" name="organization_url" class="form-control" value="{{ old('organization_url', $bowler->organization_url ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>所属先 郵便番号</label>
        <input type="text" name="organization_zip" class="form-control" value="{{ old('organization_zip', $bowler->organization_zip ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>所属先 住所</label>
        <input type="text" name="organization_addr1" class="form-control" value="{{ old('organization_addr1', $bowler->organization_addr1 ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>所属先 建物・屋号</label>
        <input type="text" name="organization_addr2" class="form-control" value="{{ old('organization_addr2', $bowler->organization_addr2 ?? '') }}">
      </div>

      <div class="col-12 mt-2">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="public_addr_same" name="public_addr_same_as_org" value="1"
            {{ old('public_addr_same_as_org', $bowler->public_addr_same_as_org ?? false) ? 'checked' : '' }}>
          <label class="form-check-label" for="public_addr_same">公開住所は所属先と同じ</label>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <label>公開 郵便番号</label>
        <input type="text" name="public_zip" class="form-control" value="{{ old('public_zip', $bowler->public_zip ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>公開 住所</label>
        <input type="text" name="public_addr1" class="form-control" value="{{ old('public_addr1', $bowler->public_addr1 ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>公開 建物・屋号</label>
        <input type="text" name="public_addr2" class="form-control" value="{{ old('public_addr2', $bowler->public_addr2 ?? '') }}">
      </div>

      <div class="col-md-6 mb-3">
        <label>会員種別名</label>
        <select name="membership_type" class="form-control @error('membership_type') is-invalid @enderror">
          <option value="">選んでください</option>
          @php
            $types = ["第1シード","第2シード","トーナメントプロ","講習会出席者","その他","名誉プロ・海外プロ","プロインストラクター","除名","死亡","退会員","認定プロインストラクター"];
          @endphp
          @foreach ($types as $type)
            <option value="{{ $type }}" {{ old('membership_type', $bowler->membership_type ?? '') == $type ? 'selected' : '' }}>{{ $type }}</option>
          @endforeach
        </select>
        @error('membership_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-6 mb-3">
        <label>地区長</label><br>
        <input type="hidden" name="is_district_leader" value="0">
        <input class="form-check-input" type="checkbox" id="is_district_leader" name="is_district_leader" value="1"
          {{ old('is_district_leader', (int)($bowler->is_district_leader ?? 0)) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_district_leader">この選手を地区長として登録</label>
      </div>
    </div>

    {{-- 非公開情報（管理者のみ） --}}
    <h4 data-bs-toggle="collapse" href="#private-section" role="button" aria-expanded="false" aria-controls="private-section">
      非公開情報（管理者のみ） <small class="text-muted">（クリックで開閉）</small>
    </h4>
    <div class="form-section row collapse" id="private-section">
      <div class="col-md-6 mb-3">
        <label>QRコード画像</label>
        <input type="file" name="qr_code_path" class="form-control">
        <small class="form-text text-muted">会場などで読み取れる選手用QRコードをアップロードしてください。</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>ライセンス交付日</label>
        <input type="date" name="license_issue_date" class="form-control" value="{{ old('license_issue_date', $bowler?->license_issue_date?->format('Y-m-d')) }}">
      </div>

      <div class="col-md-6 mb-3">
        <label>プロフィール写真</label>
        <input type="file" name="public_image_path" class="form-control">
        <small class="form-text text-muted">選手プロフィールに掲載する写真をアップロード（推奨：顔がはっきり写っているもの）</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>生年月日</label>
        <input type="date" name="birthdate" class="form-control" value="{{ old('birthdate', isset($bowler->birthdate) ? \Carbon\Carbon::parse($bowler->birthdate)->format('Y-m-d') : '') }}">
        <small class="form-text text-muted">生年月日を正確に入力してください。</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>連絡先電話</label>
        <input type="text" name="phone_home" class="form-control" placeholder="例：090-1234-5678" value="{{ old('phone_home', $bowler->phone_home ?? '') }}">
        <small class="form-text text-muted">事務局からの連絡が可能な番号を入力してください。</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>メールアドレス</label>
        <input type="email" name="email" class="form-control" placeholder="example@example.com" value="{{ old('email', $bowler->email ?? '') }}">
        <small class="form-text text-muted">確認用に使用されます。普段使用しているメールを推奨。</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>郵送区分</label>
        <select name="mailing_preference" class="form-control">
          <option value="">選んでください</option>
          <option value="1" {{ old('mailing_preference', $bowler->mailing_preference ?? '') == '1' ? 'selected' : '' }}>自宅</option>
          <option value="2" {{ old('mailing_preference', $bowler->mailing_preference ?? '') == '2' ? 'selected' : '' }}>勤務先</option>
        </select>
        <small class="form-text text-muted">書類などの送付先を選択してください。</small>
      </div>

      <div class="col-12 mt-2">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="mail_addr_same" name="mailing_addr_same_as_org" value="1"
            {{ old('mailing_addr_same_as_org', $bowler->mailing_addr_same_as_org ?? false) ? 'checked' : '' }}>
          <label class="form-check-label" for="mail_addr_same">送付先住所は所属先と同じ</label>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <label>送付先 郵便番号</label>
        <input type="text" name="mailing_zip" class="form-control" value="{{ old('mailing_zip', $bowler->mailing_zip ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>送付先 住所</label>
        <input type="text" name="mailing_addr1" class="form-control" value="{{ old('mailing_addr1', $bowler->mailing_addr1 ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>送付先 建物・屋号</label>
        <input type="text" name="mailing_addr2" class="form-control" value="{{ old('mailing_addr2', $bowler->mailing_addr2 ?? '') }}">
      </div>

      <div class="col-md-4 mb-3">
        <label>パスワード変更状況</label>
        <select name="password_change_status" class="form-control">
          @foreach ([2=>'未更新',1=>'確認中',0=>'更新済'] as $k=>$v)
            <option value="{{ $k }}" {{ old('password_change_status',$bowler->password_change_status ?? 2)==$k?'selected':'' }}>{{ $v }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4 mb-3">
        <label>ログインID</label>
        <input type="text" name="login_id" class="form-control" value="{{ old('login_id',$bowler->login_id ?? '') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label>マイページ仮パスワード</label>
        <input type="text" name="mypage_temp_password" class="form-control" value="{{ old('mypage_temp_password',$bowler->mypage_temp_password ?? '') }}">
      </div>

      <div class="col-12 mb-3">
        <label>自由記入欄</label>
        <textarea name="memo" class="form-control" rows="3" placeholder="その他、管理用メモがあれば記入してください。">{{ old('memo', $bowler->memo ?? '') }}</textarea>
      </div>
    </div>

    {{-- 一般公開情報（管理者管轄） --}}
    <h4 data-bs-toggle="collapse" href="#public-section" role="button" aria-expanded="false" aria-controls="public-section">
      一般公開情報（管理者管轄） <small class="text-muted">（クリックで開閉）</small>
    </h4>
    <div class="form-section row collapse" id="public-section">
      <div class="col-md-6 mb-3">
        <label>プロフィール写真</label>
        <input type="file" name="profile_image_public" class="form-control">
        <small class="form-text text-muted">
          公開プロフィール用の顔写真をアップロードしてください。<br>
          ファイル名は選手ID（m,fは小文字）にしてください。<br>
          例：m0000xxx.jpg、f0000xxx.jpg
        </small>
      </div>

      @if(isset($bowler))
        <div class="col-12">
          <h5 class="mt-2">タイトル情報</h5>
          <div class="d-flex align-items-center gap-3">
            <div><strong>タイトル数：</strong>
              <span class="fs-5">{{ $bowler->titles_count ?? ($bowler->titles->count() ?? 0) }}</span>
            </div>
          </div>

          <ul class="list-group mt-2">
            @forelse($bowler->titles as $t)
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>{{ $t->year }}年 / {{ $t->title_name }} @if($t->won_date)（{{ \Carbon\Carbon::parse($t->won_date)->format('Y-m-d') }}）@endif</div>
                <button type="submit" form="title-del-{{ $t->id }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('削除しますか？')">削除</button>
              </li>
            @empty
              <li class="list-group-item text-muted">登録されたタイトルはありません</li>
            @endforelse
          </ul>

          <div class="row row-cols-lg-auto g-2 align-items-end mt-3">
            <div class="col-12">
              <label class="form-label">大会名</label>
              <input type="text" name="title_name" form="title-add-form" class="form-control" placeholder="例：全日本選手権" required>
            </div>
            <div class="col-12">
              <label class="form-label">取得年</label>
              <input type="number" name="year" form="title-add-form" class="form-control" placeholder="2023" value="{{ \Carbon\Carbon::now()->year }}" required>
            </div>
            <div class="col-12">
              <label class="form-label">日付</label>
              <input type="date" name="won_date" form="title-add-form" class="form-control">
            </div>
            <div class="col-12">
              <button type="submit" form="title-add-form" class="btn btn-outline-primary">タイトル追加</button>
            </div>
          </div>
        </div>
      @endif

      <div class="mt-4">
        @if(isset($bowler) && $bowler?->id)
          @include('pro_bowlers.partials.awards_summary', ['bowler' => $bowler])
        @endif
      </div>

      <div class="col-md-6 mb-3 mt-3">
        <label>プロ入り</label>
        <input type="text" name="pro_entry_year" class="form-control" placeholder="例：2020" value="{{ old('pro_entry_year', $bowler->pro_entry_year ?? '') }}">
        <small class="form-text text-muted">初めてプロ登録された西暦年を入力してください。</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>生年月日（公開用）</label>
        <input type="date" name="birthdate_public" id="birthdate_public" class="form-control"
          value="{{ old('birthdate_public', isset($bowler->birthdate_public) ? \Carbon\Carbon::parse($bowler->birthdate_public)->format('Y-m-d') : '') }}">
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" id="birthdate_public_hide_year" name="birthdate_public_hide_year" value="1"
            {{ old('birthdate_public_hide_year', $bowler->birthdate_public_hide_year ?? false) ? 'checked' : '' }}>
          <label class="form-check-label" for="birthdate_public_hide_year">西暦（年）を非表示にする</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="birthdate_public_is_private" name="birthdate_public_is_private" value="1"
            {{ old('birthdate_public_is_private', $bowler->birthdate_public_is_private ?? false) ? 'checked' : '' }}>
          <label class="form-check-label" for="birthdate_public_is_private">生年月日を非公表にする</label>
        </div>
        <small class="form-text text-muted">管理用の生年月日を入力すると、未編集のあいだ自動でここに反映されます。手動で修正したい場合は直接編集してください。</small>
      </div>

      @push('scripts')
      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const adminBirth  = document.querySelector('input[name="birthdate"]');
          const publicBirth = document.getElementById('birthdate_public');
          const hideYearChk = document.getElementById('birthdate_public_hide_year');
          const privateChk  = document.getElementById('birthdate_public_is_private');
          if (!adminBirth || !publicBirth) return;
          let publicTouched = false;
          publicBirth.addEventListener('input', () => { publicTouched = true; });
          const syncIfNeeded = () => { if (privateChk && privateChk.checked) return; if (!publicTouched || !publicBirth.value) { publicBirth.value = adminBirth.value || ''; } };
          adminBirth.addEventListener('input',  syncIfNeeded);
          adminBirth.addEventListener('change', syncIfNeeded);
          syncIfNeeded();
          const applyPrivateState = () => { const off = !!(privateChk && privateChk.checked); publicBirth.disabled = off; if (hideYearChk) hideYearChk.disabled = off; publicBirth.classList.toggle('bg-light', off); };
          if (privateChk) privateChk.addEventListener('change', applyPrivateState);
          applyPrivateState();
        });
      </script>
      @endpush

      <div class="col-md-6 mb-3">
        <label>出身地</label>
        <input type="text" name="birthplace" class="form-control" placeholder="例：東京都" value="{{ old('birthplace', $bowler->birthplace ?? '') }}">
        <small class="form-text text-muted">都道府県名など簡潔に入力してください。</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>A級ライセンス取得番号</label>
        <input type="number" name="a_license_number" class="form-control" value="{{ old('a_license_number', $bowler->a_license_number ?? '') }}">
        <small class="form-text text-muted">証書に記載の番号（整数）</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>永久シード取得年月</label>
        <input type="date" name="permanent_seed_date" class="form-control" value="{{ old('permanent_seed_date', $bowler?->permanent_seed_date?->format('Y-m-d')) }}">
        <small class="form-text text-muted">永久シードを取得した年月日を入力してください。</small>
      </div>

      <div class="col-md-6 mb-3">
        <label>殿堂入り年月</label>
        <input type="date" name="hall_of_fame_date" class="form-control" value="{{ old('hall_of_fame_date', $bowler?->hall_of_fame_date?->format('Y-m-d')) }}">
        <small class="form-text text-muted">殿堂入りが決定・認定された年月日を入力してください。</small>
      </div>
    </div>

    {{-- インストラクター情報 --}}
    <h4 data-bs-toggle="collapse" href="#instructor-section" role="button" aria-expanded="false" aria-controls="instructor-section">
      インストラクター情報 <small class="text-muted">（クリックで開閉）</small>
    </h4>
    <div class="form-section row collapse" id="instructor-section">
      @php
        $instructor_items = [
          'a_class' => 'A級','b_class' => 'B級','c_class' => 'C級','master' => 'マスターコーチ',
          'coach_4' => 'スポーツ協会認定コーチ4','coach_3' => 'スポーツ協会認定コーチ3','coach_1' => 'スポーツ協会認定コーチ1',
          'kenkou' => '健康ボウリング教室開講指導員資格','school_license' => 'スクール開講資格',
        ];
      @endphp
      @foreach ($instructor_items as $field => $label)
        <div class="col-md-12 mb-2">
          <label>{{ $label }}：</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="{{ $field }}_status" value="有" id="{{ $field }}_yes"
                   {{ old($field . '_status', $bowler->{$field . '_status'} ?? '') === '有' ? 'checked' : '' }}>
            <label class="form-check-label" for="{{ $field }}_yes">有</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="{{ $field }}_status" value="無" id="{{ $field }}_no"
                   {{ old($field . '_status', $bowler->{$field . '_status'} ?? '') === '無' ? 'checked' : '' }}>
            <label class="form-check-label" for="{{ $field }}_no">無</label>
          </div>
          <input type="text" name="{{ $field }}_year" class="form-control mt-1" placeholder="取得年月（例：202304）"
                 value="{{ old($field . '_year', $bowler->{$field . '_year'} ?? '') }}">
        </div>
      @endforeach

      <div class="col-md-12 mb-3">
        <label>USBCコーチ</label>
        <select name="usbc_coach" class="form-control">
          <option value="">選んでください</option>
          @foreach (['Bronze', 'Silver', 'Gold'] as $level)
            <option value="{{ $level }}" {{ old('usbc_coach', $bowler->usbc_coach ?? '') === $level ? 'selected' : '' }}>{{ $level }}</option>
          @endforeach
        </select>
      </div>
    </div>
  @endif {{-- ========== ADMIN-ONLY END ========== --}}

  {{-- ======================================
       選手編集可能項目（常に表示・編集可）
  ======================================= --}}
  <h4 data-bs-toggle="collapse" href="#edit-section" role="button" aria-expanded="true" aria-controls="public-section">
    選手編集可能項目 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse show" id="edit-section">
    <div class="col-md-3 mb-3">
      <label>身長(cm)</label>
      <input type="number" name="height_cm" class="form-control" value="{{ old('height_cm',$bowler->height_cm ?? '') }}">
      <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" name="height_is_public" value="1" {{ old('height_is_public', $bowler->height_is_public ?? false) ? 'checked':'' }}>
        <label class="form-check-label">公開する</label>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <label>体重(kg)</label>
      <input type="number" name="weight_kg" class="form-control" value="{{ old('weight_kg',$bowler->weight_kg ?? '') }}">
      <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" name="weight_is_public" value="1" {{ old('weight_is_public', $bowler->weight_is_public ?? false) ? 'checked':'' }}>
        <label class="form-check-label">公開する</label>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <label>血液型</label>
      <input type="text" name="blood_type" class="form-control" value="{{ old('blood_type',$bowler->blood_type ?? '') }}">
      <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" name="blood_type_is_public" value="1" {{ old('blood_type_is_public', $bowler->blood_type_is_public ?? false) ? 'checked':'' }}>
        <label class="form-check-label">公開する</label>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <label>利き腕</label>
      <input type="text" name="dominant_arm" class="form-control" value="{{ old('dominant_arm',$bowler->dominant_arm ?? '') }}">
    </div>

    <div class="col-md-6 mb-3">
      <label>趣味・特技</label>
      <input type="text" name="hobby" class="form-control" placeholder="例：釣り、料理、ピアノなど" value="{{ old('hobby', $bowler->hobby ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label>ボウリング歴</label>
      <input type="text" name="bowling_history" class="form-control" placeholder="例：10年" value="{{ old('bowling_history', $bowler->bowling_history ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label>他スポーツ歴</label>
      <textarea name="other_sports_history" class="form-control" rows="3" placeholder="例：野球5年、テニス3年など">{{ old('other_sports_history', $bowler->other_sports_history ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-3">
      <label>今シーズン目標</label>
      <input type="text" name="season_goal" class="form-control" placeholder="例：シード獲得、300達成など" value="{{ old('season_goal', $bowler->season_goal ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label>師匠・コーチ</label>
      <input type="text" name="coach" class="form-control" placeholder="例：〇〇プロ、〇〇監督など" value="{{ old('coach', $bowler->coach ?? '') }}">
    </div>

    @php $sponsors = [['key'=>'a','label'=>'A'],['key'=>'b','label'=>'B'],['key'=>'c','label'=>'C']]; @endphp
    @foreach ($sponsors as $s)
      <div class="row g-3 align-items-end mb-2">
        <div class="col-12 col-md-6">
          <label class="form-label">スポンサー{{ $s['label'] }}</label>
          <input type="text" name="sponsor_{{ $s['key'] }}" class="form-control" placeholder="例：株式会社〇〇" value="{{ old('sponsor_'.$s['key'], $bowler->{'sponsor_'.$s['key']} ?? '') }}">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">スポンサー{{ $s['label'] }} URL</label>
          <input type="text" name="sponsor_{{ $s['key'] }}_url" class="form-control" placeholder="https://example.com" value="{{ old('sponsor_'.$s['key'].'_url', $bowler->{'sponsor_'.$s['key'].'_url'} ?? '') }}">
        </div>
      </div>
    @endforeach

    <div class="col-md-12 mb-3">
      <label>用品契約</label>
      <input type="text" name="equipment_contract" class="form-control" value="{{ old('equipment_contract',$bowler->equipment_contract ?? '') }}">
    </div>
    <div class="col-md-12 mb-3">
      <label>主な指導歴</label>
      <textarea name="coaching_history" class="form-control" rows="2">{{ old('coaching_history',$bowler->coaching_history ?? '') }}</textarea>
    </div>
    <div class="col-md-12 mb-3">
      <label>座右の銘</label>
      <input type="text" name="motto" class="form-control" value="{{ old('motto',$bowler->motto ?? '') }}">
    </div>

    <div class="col-md-12 mb-3">
      <label>セールスポイント</label>
      <textarea name="selling_point" class="form-control" rows="3" placeholder="自分の強みやアピールポイントを入力してください。">{{ old('selling_point', $bowler->selling_point ?? '') }}</textarea>
    </div>
    <div class="col-md-12 mb-3">
      <label>その他自由入力欄</label>
      <textarea name="free_comment" class="form-control" rows="3" placeholder="伝えたいことがあれば自由にご記入ください。">{{ old('free_comment', $bowler->free_comment ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-3">
      <label>フェイスブック</label>
      <input type="text" name="facebook" class="form-control" placeholder="https://facebook.com/xxxxx" value="{{ old('facebook', $bowler->facebook ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label>ツイッター</label>
      <input type="text" name="twitter" class="form-control" placeholder="https://twitter.com/xxxxx" value="{{ old('twitter', $bowler->twitter ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label>インスタグラム</label>
      <input type="text" name="instagram" class="form-control" placeholder="https://instagram.com/xxxxx" value="{{ old('instagram', $bowler->instagram ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label>ランクシーカー</label>
      <input type="text" name="rankseeker" class="form-control" placeholder="https://rankseeker.net/xxxxx" value="{{ old('rankseeker', $bowler->rankseeker ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label>JBC公認ドリラー資格</label><br>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="jbc_driller_cert" id="jbc_yes" value="有" {{ old('jbc_driller_cert', $bowler->jbc_driller_cert ?? '') === '有' ? 'checked' : '' }}>
        <label class="form-check-label" for="jbc_yes">有</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="jbc_driller_cert" id="jbc_no" value="無" {{ old('jbc_driller_cert', $bowler->jbc_driller_cert ?? '') === '無' ? 'checked' : '' }}>
        <label class="form-check-label" for="jbc_no">無</label>
      </div>
      <small class="form-text text-muted">JBCが発行する公認ドリラー資格の有無を選択してください。</small>
    </div>
  </div>

  <div class="mt-4">
    <button type="submit" class="btn btn-primary">{{ isset($bowler) ? '更新' : '登録' }}</button>
    @if (isset($bowler) && $isAdmin)
      <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="btn btn-sm btn-outline-primary">編集</a>
    @endif
    <a href="{{ route('athlete.index') }}" class="btn btn-secondary">キャンセル</a>
  </div>
</form>

@if(isset($bowler))
  @foreach ($bowler->titles as $t)
    <form id="title-del-{{ $t->id }}" method="POST" action="{{ route('pro_bowler_titles.destroy', [$bowler->id, $t->id]) }}" class="d-none">
      @csrf @method('DELETE')
    </form>
  @endforeach
  <form id="title-add-form" method="POST" action="{{ route('pro_bowler_titles.store', $bowler->id) }}" class="d-none">
    @csrf
  </form>
@endif
@endsection
