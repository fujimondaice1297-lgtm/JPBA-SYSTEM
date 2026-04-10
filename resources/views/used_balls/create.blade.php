@extends('layouts.app')

@section('content')
@php
    $viewer = auth()->user();
    $isStaff = $viewer?->isAdmin() || $viewer?->isEditor();
    $defaultLicenseNo = old('license_no', $isStaff ? request('license_no') : ($viewer?->pro_bowler_license_no ?? request('license_no')));
    $selectedInspectionNumber = old('inspection_number', '');
    $selectedRegisteredAt = old('registered_at', now()->toDateString());
@endphp

<div class="container">
    <h2 class="mb-4">使用ボール 登録フォーム</h2>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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

    <div class="card mb-4">
        <div class="card-header fw-bold">入力ルール</div>
        <div class="card-body small">
            <div>・検量証番号を入力すると、<strong>使用可能</strong> として扱います。</div>
            <div>・検量証番号が空欄なら、<strong>仮登録 / 検量証待ち</strong> として扱います。</div>
            <div>・会員画面では、自分のライセンス番号での登録だけを想定しています。</div>
        </div>
    </div>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a href="{{ route('used_balls.index') }}" class="btn btn-secondary">使用ボール一覧へ</a>
        <a href="{{ route('registered_balls.index') }}" class="btn btn-outline-secondary">登録ボール一覧へ</a>
        <a href="{{ route('tournament.entry.select') }}" class="btn btn-outline-secondary">大会エントリー選択へ</a>
    </div>

    <form method="POST" action="{{ route('used_balls.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label for="license_no" class="form-label">プロライセンス番号 <span class="text-danger">*</span></label>
                <input
                    type="text"
                    name="license_no"
                    id="license_no"
                    class="form-control"
                    value="{{ $defaultLicenseNo }}"
                    placeholder="例：M00001297"
                    {{ $isStaff ? '' : 'readonly' }}
                    required
                >
                @unless($isStaff)
                    <div class="form-text">会員は自分のライセンス番号で登録します。</div>
                @endunless
            </div>

            <div class="col-md-6">
                <label for="manufacturer" class="form-label">メーカーで絞り込み</label>
                <select
                    name="manufacturer"
                    id="manufacturer"
                    class="form-select"
                    onchange="location.href='{{ route('used_balls.create') }}?manufacturer=' + encodeURIComponent(this.value)"
                >
                    <option value="">選択してください</option>
                    @foreach($manufacturers as $manufacturer)
                        <option value="{{ $manufacturer }}" {{ request('manufacturer') == $manufacturer ? 'selected' : '' }}>
                            {{ $manufacturer }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-12">
                <label for="approved_ball_id" class="form-label">使用ボール名 <span class="text-danger">*</span></label>
                <select name="approved_ball_id" id="approved_ball_id" class="form-select" required>
                    <option value="">選択してください</option>
                    @foreach($balls as $ball)
                        <option value="{{ $ball->id }}" {{ (string) old('approved_ball_id') === (string) $ball->id ? 'selected' : '' }}>
                            {{ $ball->manufacturer }} - {{ $ball->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">メーカー絞り込み後にボールを選択します。</div>
            </div>

            <div class="col-md-6">
                <label for="serial_number" class="form-label">シリアルナンバー <span class="text-danger">*</span></label>
                <input
                    type="text"
                    name="serial_number"
                    id="serial_number"
                    class="form-control"
                    value="{{ old('serial_number') }}"
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
            </div>

            <div class="col-md-6">
                <label class="form-label">状態</label>
                <div class="form-control bg-light" id="used_ball_status_preview">
                    {{ $selectedInspectionNumber !== '' ? '使用可能（検量証あり）' : '仮登録（検量証待ち）' }}
                </div>
            </div>

            <div class="col-md-6" id="expires_group" style="{{ $selectedInspectionNumber !== '' ? '' : 'display:none;' }}">
                <label for="expires_at_preview" class="form-label">有効期限（自動計算）</label>
                <input type="date" id="expires_at_preview" class="form-control" value="" readonly>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">登録する</button>
            <a href="{{ route('used_balls.index') }}" class="btn btn-secondary">戻る</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inspectionInput = document.getElementById('inspection_number');
    const registeredAtInput = document.getElementById('registered_at');
    const expiresGroup = document.getElementById('expires_group');
    const expiresPreview = document.getElementById('expires_at_preview');
    const statusPreview = document.getElementById('used_ball_status_preview');

    function calcExpire() {
        const hasInspection = inspectionInput.value.trim() !== '';
        statusPreview.textContent = hasInspection ? '使用可能（検量証あり）' : '仮登録（検量証待ち）';
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

    inspectionInput.addEventListener('input', calcExpire);
    registeredAtInput.addEventListener('change', calcExpire);

    calcExpire();
});
</script>
@endpush