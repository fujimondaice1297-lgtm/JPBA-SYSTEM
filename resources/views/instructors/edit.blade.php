@extends('layouts.app')

@section('content')
<main class="flex-fill px-4 py-4">
  <h3 class="fw-bold mb-4">インストラクター編集</h3>

  @php
    $gradeOptions = $grades ?? ["C級", "準B級", "B級", "準A級", "A級", "2級", "1級"];
    $renewalStatusOptions = $renewalStatuses ?? ['pending' => '未更新', 'renewed' => '更新済み', 'expired' => '期限切れ'];
    $submitType = old('instructor_type', $instructor->instructor_category === 'certified' ? 'certified' : 'pro');
    $displayTypeLabel = match ($instructor->instructor_category) {
        'pro_bowler' => 'プロボウラー',
        'pro_instructor' => 'プロインストラクター',
        'certified' => '認定インストラクター',
        default => 'プロ系',
    };
    $isManual = ($instructor->source_type ?? null) === 'manual';
    $isLinkableSyncedCertified = ($instructor->source_type ?? null) === 'auth_instructor_csv' && $instructor->instructor_category === 'certified';
    $candidateBowlers = $linkableProBowlers ?? collect();
    $isAdmin = auth()->check() && auth()->user()->isAdmin();
  @endphp

  @if (session('success'))
    <div class="alert alert-success">
      {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger">
      {{ session('error') }}
    </div>
  @endif

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

  @unless ($isManual)
    <div class="alert alert-warning">
      このレコードは同期元データです。識別子と種別は固定です。必要なら元データ側の修正を優先してください。
    </div>
  @endunless

  <div class="border rounded p-3 bg-light mb-4">
    <div class="row g-3">
      <div class="col-md-3">
        <div class="text-muted small">取込元</div>
        <div class="fw-bold">{{ $instructor->source_type_label }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">状態</div>
        <div class="fw-bold">{{ $instructor->current_state_label }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">現在の種別</div>
        <div class="fw-bold">{{ $instructor->type_label }}</div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">履歴理由</div>
        <div class="fw-bold">{{ $instructor->supersede_reason_label }}</div>
      </div>
    </div>
  </div>

  @if ($isManual && $isAdmin)
    <div class="border rounded p-3 bg-light mb-4">
      <div class="fw-bold mb-2">退会処理</div>

      @if ($instructor->is_current)
        <p class="text-muted mb-3">
          この操作は物理削除ではありません。対象レコードを <strong>退会済み / history</strong> に変更します。
        </p>

        <form method="POST" action="{{ route('admin.instructors.destroy', $instructor->id) }}" onsubmit="return confirm('このインストラクターを退会済みにします。物理削除はされません。よろしいですか？');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-outline-danger">退会済みにする</button>
        </form>
      @else
        <p class="mb-0 text-muted">このレコードはすでに履歴化されています。</p>
      @endif
    </div>
  @endif

  <form method="POST" action="{{ route('instructors.update', $instructor->id) }}">
    @csrf
    @method('PUT')

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">インストラクター種別<span class="text-danger">*</span></label>

        @if ($isManual)
          <select name="instructor_type" id="instructor_type" class="form-select" required>
            <option value="pro" {{ $submitType === 'pro' ? 'selected' : '' }}>プロ系（自動判定）</option>
            <option value="certified" {{ $submitType === 'certified' ? 'selected' : '' }}>認定インストラクター</option>
          </select>
          <div class="form-text">
            プロ系は、入力したライセンスNo が既存の pro_bowlers に一致した場合、
            <strong>member_class</strong> を見て
            <strong>プロボウラー / プロインストラクター</strong>
            を自動判定します。
          </div>
        @else
          <input type="hidden" name="instructor_type" value="{{ $submitType }}">
          <input type="text" class="form-control" value="{{ $displayTypeLabel }}" readonly>
        @endif
      </div>

      <div class="col-md-6 {{ $submitType === 'certified' ? 'd-none' : '' }}" id="license_no_group">
        <label class="form-label">ライセンスNo<span class="text-danger">*</span></label>

        @if ($isManual)
          <input type="text" name="license_no" id="license_no" class="form-control" value="{{ old('license_no', $instructor->license_no) }}">
        @else
          <input type="hidden" name="license_no" value="{{ $instructor->license_no }}">
          <input type="text" id="license_no" class="form-control" value="{{ $instructor->license_no }}" readonly>
        @endif
      </div>

      <div class="col-md-6 {{ $submitType === 'certified' ? '' : 'd-none' }}" id="cert_no_group">
        <label class="form-label">認定番号<span class="text-danger">*</span></label>

        @if ($isManual)
          <input type="text" name="cert_no" id="cert_no" class="form-control" value="{{ old('cert_no', $instructor->cert_no) }}">
        @else
          <input type="hidden" name="cert_no" value="{{ $instructor->cert_no }}">
          <input type="text" id="cert_no" class="form-control" value="{{ $instructor->cert_no }}" readonly>
        @endif
      </div>

      <div class="col-md-6">
        <label class="form-label">氏名<span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $instructor->name) }}" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">フリガナ</label>
        <input type="text" name="name_kana" class="form-control" value="{{ old('name_kana', $instructor->name_kana) }}">
      </div>

      <div class="col-md-6">
        <label class="form-label">性別<span class="text-danger">*</span></label>
        <select name="sex" class="form-select" required>
          <option value="">選択してください</option>
          <option value="1" {{ old('sex', $instructor->sex ? '1' : '0') === '1' ? 'selected' : '' }}>男性</option>
          <option value="0" {{ old('sex', $instructor->sex ? '1' : '0') === '0' ? 'selected' : '' }}>女性</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">地区</label>
        <select name="district_id" class="form-select">
          <option value="">選択してください</option>
          @foreach ($districts as $district)
            <option value="{{ $district->id }}" {{ (string) old('district_id', $instructor->district_id) === (string) $district->id ? 'selected' : '' }}>
              {{ $district->label }}
            </option>
          @endforeach
        </select>
      </div>

      @if ($isLinkableSyncedCertified)
        <div class="col-12">
          <div class="border rounded p-3 bg-light">
            <div class="fw-bold mb-2">プロボウラー結線</div>

            <label class="form-label">結線先候補</label>
            <select name="linked_pro_bowler_id" class="form-select">
              <option value="">未結線のまま</option>
              @foreach ($candidateBowlers as $bowler)
                <option value="{{ $bowler->id }}" {{ (string) old('linked_pro_bowler_id', $instructor->pro_bowler_id) === (string) $bowler->id ? 'selected' : '' }}>
                  {{ $bowler->license_no }} / {{ $bowler->name_kanji }}{{ $bowler->name_kana ? ' / ' . $bowler->name_kana : '' }}{{ optional($bowler->district)->label ? ' / ' . $bowler->district->label : '' }}
                </option>
              @endforeach
            </select>

            <div class="form-text mt-2">
              ライセンス番号一致・氏名一致などから候補を表示しています。誤結線防止のため、候補が無い場合は未結線のままにしてください。
            </div>
          </div>
        </div>
      @endif

      <div class="col-md-6">
        <label class="form-label">資格等級</label>
        <select name="grade" class="form-select">
          <option value="">選択してください</option>
          @foreach ($gradeOptions as $grade)
            <option value="{{ $grade }}" {{ old('grade', $instructor->grade) === $grade ? 'selected' : '' }}>
              {{ $grade }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label d-block">有効</label>
        <input type="hidden" name="is_active" value="0">
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $instructor->is_active ? '1' : '0') == '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="is_active">有効にする</label>
        </div>
      </div>

      <div class="col-md-2">
        <label class="form-label d-block">表示</label>
        <input type="hidden" name="is_visible" value="0">
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="is_visible" id="is_visible" value="1" {{ old('is_visible', $instructor->is_visible ? '1' : '0') == '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="is_visible">表示する</label>
        </div>
      </div>

      <div class="col-md-4">
        <label class="form-label d-block">補助資格</label>
        <input type="hidden" name="coach_qualification" value="0">
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="coach_qualification" id="coach_qualification" value="1" {{ old('coach_qualification', $instructor->coach_qualification ? '1' : '0') == '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="coach_qualification">スクール開講資格等あり</label>
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label">更新年度</label>
        <input type="number" name="renewal_year" class="form-control" value="{{ old('renewal_year', $instructor->renewal_year) }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">更新期限</label>
        <input type="date" name="renewal_due_on" class="form-control" value="{{ old('renewal_due_on', optional($instructor->renewal_due_on)->format('Y-m-d')) }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">更新状態</label>
        <select name="renewal_status" class="form-select">
          <option value="">選択してください</option>
          @foreach ($renewalStatusOptions as $statusKey => $statusLabel)
            <option value="{{ $statusKey }}" {{ old('renewal_status', $instructor->renewal_status) === $statusKey ? 'selected' : '' }}>
              {{ $statusLabel }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">更新日</label>
        <input type="date" name="renewed_at" class="form-control" value="{{ old('renewed_at', optional($instructor->renewed_at)->format('Y-m-d')) }}">
      </div>

      <div class="col-12">
        <label class="form-label">更新備考</label>
        <textarea name="renewal_note" class="form-control" rows="3">{{ old('renewal_note', $instructor->renewal_note) }}</textarea>
      </div>
    </div>

    <div class="mt-4">
      <button type="submit" class="btn btn-primary">更新</button>
      <a href="{{ route('instructors.index') }}" class="btn btn-secondary">キャンセル</a>
    </div>
  </form>
</main>

@if ($isManual)
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
@endif
@endsection