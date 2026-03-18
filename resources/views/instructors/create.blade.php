@extends('layouts.app')

@section('content')
<main class="flex-fill px-4 py-4">
  <h3 class="fw-bold mb-4">インストラクター新規登録</h3>

  @php
    $gradeOptions = $grades ?? ["C級", "準B級", "B級", "準A級", "A級", "2級", "1級"];
    $oldType = old('instructor_type', 'pro');
  @endphp

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

  <form method="POST" action="{{ route('instructors.store') }}">
    @csrf

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">インストラクター種別<span class="text-danger">*</span></label>
        <select name="instructor_type" id="instructor_type" class="form-select" required>
          <option value="pro" {{ $oldType === 'pro' ? 'selected' : '' }}>プロインストラクター</option>
          <option value="certified" {{ $oldType === 'certified' ? 'selected' : '' }}>認定インストラクター</option>
        </select>
        <div class="form-text">
          プロでライセンスNoが既存の pro_bowlers に存在する場合、一覧上は「プロボウラー」として扱われます。
        </div>
      </div>

      <div class="col-md-6" id="license_no_group">
        <label class="form-label">ライセンスNo<span class="text-danger">*</span></label>
        <input
          type="text"
          name="license_no"
          id="license_no"
          class="form-control"
          value="{{ old('license_no') }}"
        >
      </div>

      <div class="col-md-6 d-none" id="cert_no_group">
        <label class="form-label">認定番号<span class="text-danger">*</span></label>
        <input
          type="text"
          name="cert_no"
          id="cert_no"
          class="form-control"
          value="{{ old('cert_no') }}"
        >
      </div>

      <div class="col-md-6">
        <label class="form-label">氏名<span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">フリガナ</label>
        <input type="text" name="name_kana" class="form-control" value="{{ old('name_kana') }}">
      </div>

      <div class="col-md-6">
        <label class="form-label">性別<span class="text-danger">*</span></label>
        <select name="sex" class="form-select" required>
          <option value="">選択してください</option>
          <option value="1" {{ old('sex') === '1' ? 'selected' : '' }}>男性</option>
          <option value="0" {{ old('sex') === '0' ? 'selected' : '' }}>女性</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">地区</label>
        <select name="district_id" class="form-select">
          <option value="">選択してください</option>
          @foreach ($districts as $district)
            <option value="{{ $district->id }}" {{ (string) old('district_id') === (string) $district->id ? 'selected' : '' }}>
              {{ $district->label }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">資格等級<span class="text-danger">*</span></label>
        <select name="grade" class="form-select" required>
          <option value="">選択してください</option>
          @foreach ($gradeOptions as $grade)
            <option value="{{ $grade }}" {{ old('grade') === $grade ? 'selected' : '' }}>
              {{ $grade }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label d-block">有効</label>
        <input type="hidden" name="is_active" value="0">
        <div class="form-check mt-2">
          <input
            class="form-check-input"
            type="checkbox"
            name="is_active"
            id="is_active"
            value="1"
            {{ old('is_active', '1') == '1' ? 'checked' : '' }}
          >
          <label class="form-check-label" for="is_active">有効にする</label>
        </div>
      </div>

      <div class="col-md-2">
        <label class="form-label d-block">表示</label>
        <input type="hidden" name="is_visible" value="0">
        <div class="form-check mt-2">
          <input
            class="form-check-input"
            type="checkbox"
            name="is_visible"
            id="is_visible"
            value="1"
            {{ old('is_visible', '1') == '1' ? 'checked' : '' }}
          >
          <label class="form-check-label" for="is_visible">表示する</label>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label d-block">補助資格</label>
        <input type="hidden" name="coach_qualification" value="0">
        <div class="form-check mt-2">
          <input
            class="form-check-input"
            type="checkbox"
            name="coach_qualification"
            id="coach_qualification"
            value="1"
            {{ old('coach_qualification') == '1' ? 'checked' : '' }}
          >
          <label class="form-check-label" for="coach_qualification">スクール開講資格等あり</label>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <button type="submit" class="btn btn-primary">登録</button>
      <a href="{{ route('instructors.index') }}" class="btn btn-secondary">キャンセル</a>
    </div>
  </form>
</main>

@push('scripts')
<script>
  (function () {
    const typeSelect = document.getElementById('instructor_type');
    const licenseGroup = document.getElementById('license_no_group');
    const certGroup = document.getElementById('cert_no_group');
    const licenseInput = document.getElementById('license_no');
    const certInput = document.getElementById('cert_no');

    function syncTypeUI() {
      const type = typeSelect.value;

      if (type === 'certified') {
        licenseGroup.classList.add('d-none');
        certGroup.classList.remove('d-none');
        licenseInput.required = false;
        certInput.required = true;
      } else {
        licenseGroup.classList.remove('d-none');
        certGroup.classList.add('d-none');
        licenseInput.required = true;
        certInput.required = false;
      }
    }

    typeSelect.addEventListener('change', syncTypeUI);
    syncTypeUI();
  })();
</script>
@endpush
@endsection