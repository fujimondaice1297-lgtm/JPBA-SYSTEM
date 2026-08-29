@extends('layouts.app')

@section('content')
@php
    $selectedLicenseNo = $fixedLicenseNo ?? old('license_no', request('license_no'));
    $selectedApprovedBallId = (string) old('approved_ball_id', request('approved_ball_id'));
    $selectedSerialNumber = old('serial_number', request('serial_number'));
    $selectedRegisteredAt = old('registered_at', request('registered_at', now()->format('Y-m-d')));
    $selectedInspectionNumber = old('inspection_number', request('inspection_number', ''));
    $selectedBall = collect($approvedBalls ?? [])->firstWhere('id', (int) $selectedApprovedBallId);
    $selectedBrand = old('brand_filter', $selectedBall?->registration_brand ?? '');
    $selectedReleaseYear = old('release_year_filter', $selectedBall->release_year ?? '');
    $selectedBowler = collect($proBowlers ?? [])->firstWhere('license_no', $selectedLicenseNo);
    $returnTo = old('return_to', request('return_to'));
    $entryId = old('entry_id', request('entry_id'));
@endphp

<div class="container">
    <h2 class="mb-4">登録ボール 新規作成</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>入力内容に誤りがあります：</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-bold">入力ルール</div>
        <div class="card-body small">
            <div>・検量証番号を入力すると、<strong>登録日から1年−1日</strong> を有効期限として扱います。</div>
            <div>・検量証番号を空欄のまま登録すると、<strong>仮登録</strong> として扱います。</div>
            <div>・「本登録へ」から来た場合は、ライセンス番号・登録ボール・シリアル番号が自動で入ります。</div>
        </div>
    </div>

    <form method="POST" action="{{ route('registered_balls.store') }}">
        @csrf
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <input type="hidden" name="entry_id" value="{{ $entryId }}">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="license_no" class="form-label">プロライセンス番号 <span class="text-danger">*</span></label>

                @if(!empty($fixedLicenseNo))
                    <input
                        type="text"
                        name="license_no"
                        id="license_no"
                        class="form-control"
                        value="{{ $fixedLicenseNo }}"
                        readonly
                        required
                    >
                    <div class="form-text">
                        会員画面では自分のライセンス番号で固定されます。
                        @if($selectedBowler)
                            （{{ $selectedBowler->name_kanji }}）
                        @endif
                    </div>
                @else
                    <input
                        type="text"
                        name="license_no"
                        id="license_no"
                        class="form-control"
                        value="{{ $selectedLicenseNo }}"
                        list="registered-ball-license-options"
                        placeholder="例：M00001234"
                        required
                    >
                    <datalist id="registered-ball-license-options">
                        @foreach($proBowlers as $bowler)
                            <option value="{{ $bowler->license_no }}">{{ $bowler->name_kanji }}</option>
                        @endforeach
                    </datalist>
                    <div class="form-text">ライセンス番号を直接入力できます。候補一覧からも選べます。</div>
                @endif
            </div>

            <div class="col-md-6">
                <label class="form-label">絞り込み補助</label>
                <div class="row g-2">
                    <div class="col-md-6">
                        <select id="brand_filter" name="brand_filter" class="form-select">
                            <option value="">ブランドで絞り込み</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand }}" {{ (string) $selectedBrand === (string) $brand ? 'selected' : '' }}>
                                    {{ $brand }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select id="release_year_filter" name="release_year_filter" class="form-select">
                            <option value="">USBC承認年で絞り込み</option>
                            @foreach ($years as $year)
                                <option value="{{ $year }}" {{ (string) $selectedReleaseYear === (string) $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <label for="approved_ball_id" class="form-label">登録ボール <span class="text-danger">*</span></label>
                <select name="approved_ball_id" id="approved_ball_id" class="form-select" required>
                    <option value="">選択してください</option>
                    @foreach($approvedBalls as $ball)
                        <option
                            value="{{ $ball->id }}"
                            data-brand="{{ $ball->registration_brand }}"
                            data-release-year="{{ $ball->release_year }}"
                            data-usbc-status="{{ $ball->usbc_match_status ?? 'unchecked' }}"
                            {{ $selectedApprovedBallId === (string) $ball->id ? 'selected' : '' }}
                        >
                            {{ $ball->registration_brand }} - {{ $ball->name }}@if($ball->registration_period_label)（{{ $ball->registration_period_label }}）@endif
                        </option>
                    @endforeach
                </select>
                <div id="usbc_ball_warning" class="alert alert-danger mt-2 mb-0 d-none" role="alert"></div>
            </div>

            <div class="col-md-6">
                <label for="serial_number" class="form-label">シリアルナンバー <span class="text-danger">*</span></label>
                <input
                    type="text"
                    name="serial_number"
                    id="serial_number"
                    class="form-control"
                    value="{{ $selectedSerialNumber }}"
                    placeholder="例：BK00074"
                    required
                >
            </div>

            <div class="col-md-6">
                <label for="registered_at" class="form-label">検量日／登録日 <span class="text-danger">*</span></label>
                <input
                    type="date"
                    name="registered_at"
                    id="registered_at"
                    class="form-control"
                    value="{{ $selectedRegisteredAt }}"
                    required
                >
            </div>

            <div class="col-md-6">
                <label for="inspection_number" class="form-label">検量証番号</label>
                <input
                    type="text"
                    name="inspection_number"
                    id="inspection_number"
                    class="form-control"
                    value="{{ $selectedInspectionNumber }}"
                    placeholder="未入力なら仮登録"
                >
                <div class="form-text">未入力のままでも登録できます。その場合は仮登録です。</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">状態</label>
                <div class="form-control bg-light" id="registered_ball_status_preview">
                    {{ $selectedInspectionNumber !== '' ? '本登録（検量証あり）' : '仮登録（検量証待ち）' }}
                </div>
            </div>

            <div class="col-md-6" id="expires_group" style="{{ $selectedInspectionNumber !== '' ? '' : 'display:none;' }}">
                <label for="expires_at_preview" class="form-label">有効期限（自動計算）</label>
                <input
                    type="date"
                    id="expires_at_preview"
                    class="form-control"
                    value=""
                    readonly
                >
            </div>
        </div>

        <div class="mt-4 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">登録</button>
            <a href="{{ route('registered_balls.index') }}" class="btn btn-secondary">戻る</a>
            <a href="{{ route('used_balls.index') }}" class="btn btn-outline-secondary">使用ボール一覧へ</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const brandFilter = document.getElementById('brand_filter');
    const releaseYearFilter = document.getElementById('release_year_filter');
    const approvedBallSelect = document.getElementById('approved_ball_id');
    const inspectionInput = document.getElementById('inspection_number');
    const registeredAtInput = document.getElementById('registered_at');
    const expiresGroup = document.getElementById('expires_group');
    const expiresPreview = document.getElementById('expires_at_preview');
    const statusPreview = document.getElementById('registered_ball_status_preview');
    const usbcWarning = document.getElementById('usbc_ball_warning');

    const originalOptions = Array.from(approvedBallSelect.querySelectorAll('option'))
        .filter(option => option.value !== '')
        .map(option => ({
            value: option.value,
            text: option.textContent,
            brand: option.dataset.brand || '',
            releaseYear: option.dataset.releaseYear || '',
            usbcStatus: option.dataset.usbcStatus || 'unchecked',
            selected: option.selected,
        }));

    function rebuildApprovedBallOptions() {
        const currentValue = approvedBallSelect.value;
        const brand = brandFilter.value;
        const releaseYear = releaseYearFilter.value;

        approvedBallSelect.innerHTML = '<option value="">選択してください</option>';

        originalOptions.forEach(option => {
            const hitBrand = !brand || option.brand === brand;
            const hitReleaseYear = !releaseYear || String(option.releaseYear) === String(releaseYear);

            if (hitBrand && hitReleaseYear) {
                const el = document.createElement('option');
                el.value = option.value;
                el.textContent = option.text;
                el.dataset.brand = option.brand;
                el.dataset.releaseYear = option.releaseYear;
                el.dataset.usbcStatus = option.usbcStatus;
                if (currentValue && currentValue === option.value) {
                    el.selected = true;
                }
                approvedBallSelect.appendChild(el);
            }
        });

        if (currentValue && !Array.from(approvedBallSelect.options).some(opt => opt.value === currentValue)) {
            const fallback = originalOptions.find(option => option.value === currentValue);
            if (fallback) {
                const el = document.createElement('option');
                el.value = fallback.value;
                el.textContent = fallback.text + '（現在選択中）';
                el.dataset.brand = fallback.brand;
                el.dataset.releaseYear = fallback.releaseYear;
                el.dataset.usbcStatus = fallback.usbcStatus;
                el.selected = true;
                approvedBallSelect.appendChild(el);
            }
        }

        updateUsbcWarning();
    }

    function updateUsbcWarning() {
        const selected = approvedBallSelect.options[approvedBallSelect.selectedIndex];
        const usbcStatus = selected?.dataset.usbcStatus || '';

        usbcWarning.classList.remove('alert-danger', 'alert-warning');
        if (!selected || !selected.value || usbcStatus === 'matched') {
            usbcWarning.classList.add('d-none');
            usbcWarning.textContent = '';
            return;
        }

        usbcWarning.classList.remove('d-none');
        if (usbcStatus === 'not_listed') {
            usbcWarning.classList.add('alert-danger');
            usbcWarning.textContent = 'アブプールリストに記載のないボールです';
            return;
        }

        usbcWarning.classList.add('alert-warning');
        usbcWarning.textContent = usbcStatus === 'ambiguous'
            ? 'アブプールリストとの照合結果が要確認のボールです。'
            : 'アブプールリストとの照合が未実施のボールです。';
    }

    function calcExpire() {
        const hasInspection = inspectionInput.value.trim() !== '';
        statusPreview.textContent = hasInspection ? '本登録（検量証あり）' : '仮登録（検量証待ち）';
        expiresGroup.style.display = hasInspection ? '' : 'none';

        if (!hasInspection) {
            expiresPreview.value = '';
            return;
        }

        const date = new Date(registeredAtInput.value);
        if (isNaN(date)) {
            expiresPreview.value = '';
            return;
        }

        const expires = new Date(date);
        expires.setFullYear(expires.getFullYear() + 1);
        expires.setDate(expires.getDate() - 1);

        const y = expires.getFullYear();
        const m = ('0' + (expires.getMonth() + 1)).slice(-2);
        const d = ('0' + expires.getDate()).slice(-2);
        expiresPreview.value = `${y}-${m}-${d}`;
    }

    brandFilter.addEventListener('change', rebuildApprovedBallOptions);
    releaseYearFilter.addEventListener('change', rebuildApprovedBallOptions);
    approvedBallSelect.addEventListener('change', updateUsbcWarning);
    inspectionInput.addEventListener('input', calcExpire);
    registeredAtInput.addEventListener('change', calcExpire);

    rebuildApprovedBallOptions();
    calcExpire();
});
</script>
@endpush
