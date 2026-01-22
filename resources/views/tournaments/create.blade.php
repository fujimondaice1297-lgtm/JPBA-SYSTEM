{{-- resources/views/tournaments/create.blade.php --}}
@extends('layouts.app')

@section('content')
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
      <select name="official_type" class="form-select @error('official_type') is-invalid @enderror" required>
        @php $official = old('official_type','official'); @endphp
        <option value="official"  {{ $official==='official' ? 'selected' : '' }}>公認</option>
        <option value="approved" {{ $official==='approved'? 'selected' : '' }}>承認</option>
        <option value="other"    {{ $official==='other'   ? 'selected' : '' }}>その他</option>
      </select>
      @error('official_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">種別（男女） <span class="text-danger">*</span></label>
      <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
        @php $g = old('gender','X'); @endphp
        <option value="M" {{ $g==='M' ? 'selected' : '' }}>男子</option>
        <option value="F" {{ $g==='F' ? 'selected' : '' }}>女子</option>
        <option value="X" {{ $g==='X' ? 'selected' : '' }}>男女/未設定</option>
      </select>
      @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label">タイトル区分</label>
      <select name="title_category" class="form-select @error('title_category') is-invalid @enderror">
        @php $tc = old('title_category','normal'); @endphp
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

    {{-- 申込期間 --}}
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

    {{-- 大会観覧（保存キー：spectator_policy） --}}
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
      <input type="checkbox" name="inspection_required" value="1"
            class="form-check-input" {{ old('inspection_required') ? 'checked' : '' }}>
      <small class="text-muted d-block">※ チェック時：検量証未入力のボールは仮登録扱い</small>
    </div>
  </div>

  {{-- 会場情報（検索式） --}}
  <div class="d-flex align-items-center justify-content-between mt-4">
    <h4 class="mb-0" data-bs-toggle="collapse" href="#t-venue" role="button" aria-expanded="true" aria-controls="t-venue">
      会場情報 <small class="text-muted">（クリックで開閉）</small>
    </h4>
    <a href="{{ route('venues.index') }}" class="btn btn-sm btn-outline-primary">
      会場を登録・一覧
    </a>
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

    {{-- 参照用（保存対象外） --}}
    <div class="col-md-6 mb-3">
      <label class="form-label">会場サイトURL</label>
      <input type="url" name="venue_website_url" id="venue_url_view"
             class="form-control" placeholder="https://example.com"
             value="{{ old('venue_website_url') }}" readonly>
      <small class="text-muted">会場検索で選択すると自動表示。送信時はDB保存しません（保持のみ）。</small>
    </div>
  </div>

  {{-- 主催・協賛等（検索＋反映ターゲット選択付き） --}}
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
        <small class="text-muted ms-2">（名称とURL。URLは任意。クリックで外部サイトへ遷移できるよう保存されます）</small>
      </div>
    @endforeach

    {{-- 自由見出し --}}
    <div class="col-12 mb-2">
      <label class="form-label">自由見出し</label>
      <div data-org-list="__free__"></div>
      <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-org-add="__free__">追加</button>
      <small class="text-muted ms-2">（例：実行委員会 / 後援（予定） など任意。URLは任意）</small>
    </div>
  </div>

  {{-- メディア / 賞金など --}}
  <h4 data-bs-toggle="collapse" href="#t-media" role="button" aria-expanded="false" aria-controls="t-media">
    メディア・賞金 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-media">
    <div class="col-md-4 mb-3">
      <label class="form-label">TV放映</label>
      <input type="text" name="broadcast" class="form-control" placeholder="例：BS××"
             value="{{ old('broadcast') }}">
      <small class="text-muted">（任意）</small>
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

    <div class="col-md-6 mb-3">
      <label class="form-label">タイトル左ロゴ</label>
      <input type="file" name="title_logo" class="form-control" accept="image/*">
      <small class="text-muted">（タイトル左に小さく表示されるロゴ画像。PNG/JPG推奨）</small>
    </div>
    {{-- 画像アップロード --}}
    <h5 class="mt-3">画像アップロード</h5>
    <div class="col-md-6 mb-3">
      <label class="form-label">ポスター画像（複数可）</label>
      <input type="file" name="image" class="form-control mb-2" accept="image/*" id="poster-input">
      {{-- ★コントローラに合わせて posters[] に統一 --}}
      <input type="file" name="posters[]" class="form-control" accept="image/*" multiple>
      <small class="text-muted">推奨：横長（16:9 〜 4:3） JPG/PNG</small>
      <div class="mt-2">
        <img id="poster-preview" src="" alt="" style="max-width: 100%; display:none; border:1px solid #e5e5e5; border-radius:8px;">
      </div>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">トップ画像</label>
      <input type="file" name="hero_image" class="form-control" accept="image/*">
      <small class="text-muted">大会名の直下に表示されるメイン画像（任意）</small>
    </div>

    {{-- ★ タイトル左ロゴ（タイトルの先頭に小さく表示／編集でも保持） --}}
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
      <label class="form-label">その他PDF（タイトル自由／複数可）</label>
      <input type="file" name="custom_files[]" class="form-control mb-2" accept="application/pdf" multiple>
      <input type="text" name="custom_titles[]" class="form-control" placeholder="1つ目のタイトル（任意：複数時は順番で使用）">
      <small class="text-muted">（複数タイトルはカンマ区切りではなく、必要なら送信後に編集で整えられます）</small>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">登録</button>
    <a href="{{ url()->previous() ?: route('tournaments.index') }}" class="btn btn-secondary">キャンセル</a>
  </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // 画像プレビュー（メイン1枚）
  const input = document.getElementById('poster-input');
  const preview = document.getElementById('poster-preview');
  if (input) {
    input.addEventListener('change', (e) => {
      const f = e.target.files?.[0];
      if (!f) { preview.style.display = 'none'; preview.src = ''; return; }
      const reader = new FileReader();
      reader.onload = (ev) => {
        preview.src = ev.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(f);
    });
  }

  // 会場検索
  const qs = (sel)=>document.querySelector(sel);
  const venueBox = qs('#venue_result');
  function renderVenue(items){
    if(!items || !items.length){ venueBox.style.display='none'; venueBox.innerHTML=''; return; }
    venueBox.innerHTML = items.map(v => `
      <div class="py-1 px-2 hover-bg" data-id="${v.id}" style="cursor:pointer;">
        <strong>${v.name}</strong><br>
        <small>${v.address ?? ''}</small>
      </div>
    `).join('');
    venueBox.style.display='block';
    venueBox.querySelectorAll('[data-id]').forEach(el=>{
      el.addEventListener('click', async () => {
        const id = el.getAttribute('data-id');
        const res = await fetch('{{ route('api.venues.show', ['id'=>'__ID__']) }}'.replace('__ID__', id));
        const v = await res.json();
        qs('#venue_id').value = v.id;
        qs('#venue_name').value = v.name ?? '';
        qs('#venue_address').value = v.address ?? '';
        qs('#venue_tel').value = v.tel ?? '';
        qs('#venue_fax').value = v.fax ?? '';
        const u = document.getElementById('venue_url_view'); if (u) u.value = v.website_url ?? '';
        venueBox.style.display='none';
      });
    });
  }
  async function doSearchVenue(){
    const q = qs('#venue_search').value.trim();
    if(q.length < 3){ renderVenue([]); return; }
    const res = await fetch(`{{ route('api.venues.search') }}?q=${encodeURIComponent(q)}`);
    const items = await res.json();
    renderVenue(items);
  }
  qs('#venue_search_btn').addEventListener('click', doSearchVenue);
  qs('#venue_search').addEventListener('input', () => {
    clearTimeout(window.__venue_timer);
    window.__venue_timer = setTimeout(doSearchVenue, 250);
  });

  // 主催・協賛 繰り返し行
  const cats = ['host','special_sponsor','sponsor','support','cooperation','__free__'];
  // ★ここが重要：行ごとに同じ添字を使う
  let ORG_SEQ = 0;
  function rowTpl(cat, name='', url=''){
    const idx = ORG_SEQ++;
    return `
      <div class="row g-2 align-items-center mb-1" data-org-row>
        <input type="hidden" name="org[${idx}][category]" value="${cat}">
        <div class="col-md-5">
          <input type="text" name="org[${idx}][name]" class="form-control" placeholder="名称" value="${name}">
        </div>
        <div class="col-md-6">
          <input type="url" name="org[${idx}][url]" class="form-control" placeholder="https://example.com" value="${url}">
        </div>
        <div class="col-md-1 d-grid">
          <button type="button" class="btn btn-outline-danger" data-org-del>&times;</button>
        </div>
      </div>`;
  }
  function addRowTo(cat, name='', url=''){
    const con = document.querySelector(`[data-org-list="${cat}"]`);
    if (!con) return;
    con.insertAdjacentHTML('beforeend', rowTpl(cat, name, url));
    con.querySelectorAll('[data-org-del]').forEach(b=> b.onclick=()=> b.closest('[data-org-row]').remove());
  }
  cats.forEach(cat=>{
    const btn = document.querySelector(`[data-org-add="${cat}"]`);
    if (!btn) return;
    btn.addEventListener('click', ()=> addRowTo(cat));
  });

  // 組織マスタ検索（ターゲット区分へ反映）
  const orgBox = document.getElementById('org_search_result');
  async function doOrgSearch(){
    const kw = (document.getElementById('org_search_kw').value || '').trim();
    if (kw.length < 3) { orgBox.style.display='none'; orgBox.innerHTML=''; return; }
    const res = await fetch(`{{ route('api.organizations.search') }}?q=${encodeURIComponent(kw)}`);
    const items = await res.json();
    if (!items.length) { orgBox.style.display='none'; orgBox.innerHTML=''; return; }
    orgBox.innerHTML = items.map(v=>`
      <div class="py-1 px-2" data-org='${JSON.stringify(v)}' style="cursor:pointer;">
        <strong>${v.name}</strong> <small class="text-muted">${v.url ?? ''}</small>
      </div>`).join('');
    orgBox.style.display='block';
    orgBox.querySelectorAll('[data-org]').forEach(el=>{
      el.onclick = ()=>{
        const v = JSON.parse(el.getAttribute('data-org'));
        const target = (document.getElementById('org_target_cat')?.value) || 'sponsor';
        addRowTo(target, v.name || '', v.url || '');
      };
    });
  }
  document.getElementById('org_search_btn').addEventListener('click', doOrgSearch);
});
</script>
@endpush
@endsection
