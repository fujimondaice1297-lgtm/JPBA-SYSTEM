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
      <label class="form-label">観客数</label>
      <input type="text" name="audience" class="form-control @error('audience') is-invalid @enderror"
             placeholder="例：12,345" value="{{ old('audience') }}">
      @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 mb-3">
      <label class="form-label d-block">検量証必須</label>
      <input type="hidden" name="inspection_required" value="0">
      <input type="checkbox" name="inspection_required" value="1"
            class="form-check-input" {{ old('inspection_required') ? 'checked' : '' }}>
      <small class="text-muted d-block">※ チェック時：検量証未入力のボールは仮登録扱い</small>
    </div>

  </div>

  {{-- 会場情報 --}}
  <h4 data-bs-toggle="collapse" href="#t-venue" role="button" aria-expanded="true" aria-controls="t-venue">
    会場情報 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse show" id="t-venue">
    <div class="col-md-6 mb-3">
      <label class="form-label">会場名</label>
      <input type="text" name="venue_name" class="form-control @error('venue_name') is-invalid @enderror"
             placeholder="例：○○ボウル" value="{{ old('venue_name') }}">
      @error('venue_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">会場住所</label>
      <input type="text" name="venue_address" class="form-control @error('venue_address') is-invalid @enderror"
             placeholder="例：東京都千代田区..." value="{{ old('venue_address') }}">
      @error('venue_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">電話番号</label>
      <input type="text" name="venue_tel" class="form-control @error('venue_tel') is-invalid @enderror"
             placeholder="例：03-1234-5678" value="{{ old('venue_tel') }}">
      @error('venue_tel')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">FAX</label>
      <input type="text" name="venue_fax" class="form-control @error('venue_fax') is-invalid @enderror"
             placeholder="例：03-1234-5679" value="{{ old('venue_fax') }}">
      @error('venue_fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
  </div>

  {{-- 主催・協賛等 --}}
  <h4 data-bs-toggle="collapse" href="#t-org" role="button" aria-expanded="false" aria-controls="t-org">
    主催・協賛等 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-org">
    <div class="col-md-6 mb-3">
      <label class="form-label">主催</label>
      <input type="text" name="host" class="form-control" placeholder="例：JPBA"
             value="{{ old('host') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">主管</label>
      <input type="text" name="supervisor" class="form-control" placeholder="例：○○実行委員会"
             value="{{ old('supervisor') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">特別協賛</label>
      <input type="text" name="special_sponsor" class="form-control" value="{{ old('special_sponsor') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">協賛</label>
      <input type="text" name="sponsor" class="form-control" value="{{ old('sponsor') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">後援</label>
      <input type="text" name="support" class="form-control" value="{{ old('support') }}">
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">公認</label>
      <input type="text" name="authorized_by" class="form-control" placeholder="例：JBC"
             value="{{ old('authorized_by') }}">
    </div>
  </div>

  {{-- メディア / 金額など --}}
  <h4 data-bs-toggle="collapse" href="#t-media" role="button" aria-expanded="false" aria-controls="t-media">
    メディア・賞金 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-media">
    <div class="col-md-4 mb-3">
      <label class="form-label">TV放映</label>
      <input type="text" name="broadcast" class="form-control" placeholder="例：BS××"
             value="{{ old('broadcast') }}">
    </div>
    <div class="col-md-4 mb-3">
      <label class="form-label">配信</label>
      <input type="text" name="streaming" class="form-control" placeholder="例：YouTube Live"
             value="{{ old('streaming') }}">
    </div>
    <div class="col-md-4 mb-3">
      <label class="form-label">賞金</label>
      <input type="text" name="prize" class="form-control" placeholder="例：¥5,000,000"
             value="{{ old('prize') }}">
    </div>
  </div>

  {{-- エントリー・資料ほか --}}
  <h4 data-bs-toggle="collapse" href="#t-etc" role="button" aria-expanded="false" aria-controls="t-etc">
    追加情報 <small class="text-muted">（クリックで開閉）</small>
  </h4>
  <div class="form-section row collapse" id="t-etc">
    <div class="col-md-6 mb-3">
      <label class="form-label">参加条件</label>
      <textarea name="entry_conditions" class="form-control" rows="4"
                placeholder="例：プロ会員のみ、アマチュア枠××名など">{{ old('entry_conditions') }}</textarea>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">資料</label>
      <textarea name="materials" class="form-control" rows="4"
                placeholder="要項・要綱のメモ等">{{ old('materials') }}</textarea>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">前年大会</label>
      <input type="text" name="previous_event" class="form-control"
             placeholder="例：第○回××オープン（2024）" value="{{ old('previous_event') }}">
    </div>

    <div class="col-md-6 mb-3">
      <label class="form-label">ポスター画像</label>
      <input type="file" name="image" class="form-control" accept="image/*" id="poster-input">
      <small class="text-muted">推奨：横長（16:9 〜 4:3） JPG/PNG</small>
      <div class="mt-2">
        <img id="poster-preview" src="" alt="" style="max-width: 100%; display:none; border:1px solid #e5e5e5; border-radius:8px;">
      </div>
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
  // 画像プレビュー
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
});
</script>
@endpush
@endsection
