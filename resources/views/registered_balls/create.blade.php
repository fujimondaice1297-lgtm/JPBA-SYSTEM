@extends('layouts.app')

@section('content')
@php
    $selectedLicenseNo = old('license_no', request('license_no'));
    $selectedApprovedBallId = (string) old('approved_ball_id', request('approved_ball_id'));
    $selectedSerialNumber = old('serial_number', request('serial_number'));
    $selectedRegisteredAt = old('registered_at', request('registered_at', now()->format('Y-m-d')));
    $selectedInspectionNumber = old('inspection_number', request('inspection_number', ''));
    $selectedBall = collect($approvedBalls ?? [])->firstWhere('id', (int) $selectedApprovedBallId);
    $selectedManufacturer = old('manufacturer_filter', $selectedBall->manufacturer ?? '');
    $selectedReleaseYear = old('release_year_filter', $selectedBall->release_year ?? '');
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
            <div>・「本登録へ」から来た場合は、ライセンス番号・承認ボール・シリアル番号が自動で入ります。</div>
        </div>
    </div>

    <form method="POST" action="{{ route('registered_balls.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label for="license_no" class="form-label">プロライセンス番号 <span class="text-danger">*</span></label>
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
            </div>

            <div class="col-md-6">
                <label class="form-label">絞り込み補助</label>
                <div class="row g-2">
                    <div class="col-md-6">
                        <select id="manufacturer_filter" name="manufacturer_filter" class="form-select">
                            <option value="">メーカーで絞り込み</option>
                            @foreach ($manufacturers as $manufacturer)
                                <option value="{{ $manufacturer }}" {{ (string) $selectedManufacturer === (string) $manufacturer ? 'selected' : '' }}>
                                    {{ $manufacturer }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select id="release_year_filter" name="release_year_filter" class="form-select">
                            <option value="">発売年で絞り込み</option>
                            @for ($year = date('Y'); $year >= 2000; $year--)
                                <option value="{{ $year }}" {{ (string) $selectedReleaseYear === (string) $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <label for="approved_ball_id" class="form-label">承認ボール <span class="text-danger">*</span></label>
                <select name="approved_ball_id" id="approved_ball_id" class="form-select" required>
                    <option value="">選択してください</option>
                    @foreach($approvedBalls as $ball)
                        <option
                            value="{{ $ball->id }}"
                            data-manufacturer="{{ $ball->manufacturer }}"
                            data-release-year="{{ $ball->release_year }}"
                            {{ $selectedApprovedBallId === (string) $ball->id ? 'selected' : '' }}
                        >
                            {{ $ball->manufacturer }} - {{ $ball->name }}@if($ball->release_year)（{{ $ball->release_year }}年）@endif
                        </option>
                    @endforeach
                </select>
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
                <label for="registered_at" class="form-label">登録日 <span class="text-danger">*</span></label>
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
    const manufacturerFilter = document.getElementById('manufacturer_filter');
    const releaseYearFilter = document.getElementById('release_year_filter');
    const approvedBallSelect = document.getElementById('approved_ball_id');
    const inspectionInput = document.getElementById('inspection_number');
    const registeredAtInput = document.getElementById('registered_at');
    const expiresGroup = document.getElementById('expires_group');
    const expiresPreview = document.getElementById('expires_at_preview');
    const statusPreview = document.getElementById('registered_ball_status_preview');

    const originalOptions = Array.from(approvedBallSelect.querySelectorAll('option'))
        .filter(option => option.value !== '')
        .map(option => ({
            value: option.value,
            text: option.textContent,
            manufacturer: option.dataset.manufacturer || '',
            releaseYear: option.dataset.releaseYear || '',
            selected: option.selected,
        }));

    function rebuildApprovedBallOptions() {
        const currentValue = approvedBallSelect.value;
        const manufacturer = manufacturerFilter.value;
        const releaseYear = releaseYearFilter.value;

        approvedBallSelect.innerHTML = '<option value="">選択してください</option>';

        originalOptions.forEach(option => {
            const hitManufacturer = !manufacturer || option.manufacturer === manufacturer;
            const hitReleaseYear = !releaseYear || String(option.releaseYear) === String(releaseYear);

            if (hitManufacturer && hitReleaseYear) {
                const el = document.createElement('option');
                el.value = option.value;
                el.textContent = option.text;
                el.dataset.manufacturer = option.manufacturer;
                el.dataset.releaseYear = option.releaseYear;
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
                el.dataset.manufacturer = fallback.manufacturer;
                el.dataset.releaseYear = fallback.releaseYear;
                el.selected = true;
                approvedBallSelect.appendChild(el);
            }
        }
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

    manufacturerFilter.addEventListener('change', rebuildApprovedBallOptions);
    releaseYearFilter.addEventListener('change', rebuildApprovedBallOptions);
    inspectionInput.addEventListener('input', calcExpire);
    registeredAtInput.addEventListener('change', calcExpire);

    rebuildApprovedBallOptions();
    calcExpire();
});
</script>
@endpush