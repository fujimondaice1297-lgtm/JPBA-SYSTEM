@extends('layouts.app')

@section('content')
@php
    $isTemporary = blank(old('inspection_number', $usedBall->inspection_number));
    $isExpired = !blank($usedBall->expires_at) && optional($usedBall->expires_at)->lt(now()->startOfDay());
@endphp

<div class="container">
    <h2 class="mb-4">使用ボール 編集</h2>

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

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-bold">現在の情報</div>
        <div class="card-body">
            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="text-muted">ライセンス番号</div>
                    <div>{{ $usedBall->proBowler?->license_no ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">氏名</div>
                    <div>{{ $usedBall->proBowler?->name_kanji ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">ボール名</div>
                    <div>{{ $usedBall->approvedBall?->manufacturer ?? '' }} {{ $usedBall->approvedBall?->name ?? '' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">シリアルナンバー</div>
                    <div>{{ $usedBall->serial_number ?? '-' }}</div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted">登録日</div>
                    <div>{{ optional($usedBall->registered_at)->format('Y-m-d') ?? '-' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">現在の有効期限</div>
                    <div>{{ optional($usedBall->expires_at)->format('Y-m-d') ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">現在の状態</div>
                    <div>
                        @if ($isTemporary)
                            <span class="badge bg-warning text-dark">仮登録</span>
                        @elseif ($isExpired)
                            <span class="badge bg-danger">期限切れ</span>
                        @else
                            <span class="badge bg-info">使用可能</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">検量証番号</div>
                    <div>{{ $usedBall->inspection_number ?: '—' }}</div>
                </div>
            </div>

            <div class="mt-3 small text-muted">
                この画面では、主に <strong>検量証番号の補正</strong> を行います。<br>
                検量証番号を空にすると仮登録へ戻り、入力すると使用可能状態へ更新します。
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('used_balls.update', $usedBall->id) }}">
        @csrf
        @method('PATCH')

        <div class="row g-3">
            <div class="col-md-6">
                <label for="inspection_number" class="form-label">検量証番号</label>
                <input
                    type="text"
                    name="inspection_number"
                    id="inspection_number"
                    class="form-control"
                    value="{{ old('inspection_number', $usedBall->inspection_number) }}"
                    placeholder="空欄で仮登録に戻す"
                >
            </div>

            <div class="col-md-6">
                <label class="form-label">更新後の状態</label>
                <div class="form-control bg-light" id="used_ball_status_preview">
                    {{ blank(old('inspection_number', $usedBall->inspection_number)) ? '仮登録（検量証待ち）' : '使用可能（検量証あり）' }}
                </div>
            </div>

            <div class="col-md-6" id="expires_group" style="{{ blank(old('inspection_number', $usedBall->inspection_number)) ? 'display:none;' : '' }}">
                <label for="expires_at_preview" class="form-label">有効期限（自動表示）</label>
                <input
                    type="date"
                    id="expires_at_preview"
                    class="form-control"
                    value="{{ optional($usedBall->expires_at)->format('Y-m-d') }}"
                    readonly
                >
                <div class="form-text">更新時点を基準に、controller 側で有効期限を再設定します。</div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">更新する</button>
            <a href="{{ route('used_balls.index') }}" class="btn btn-secondary">戻る</a>
            <a href="{{ route('registered_balls.index') }}" class="btn btn-outline-secondary">登録ボール一覧へ</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inspectionInput = document.getElementById('inspection_number');
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

        const now = new Date();
        const expires = new Date(now);
        expires.setFullYear(expires.getFullYear() + 1);
        expires.setDate(expires.getDate() - 1);

        const y = expires.getFullYear();
        const m = ('0' + (expires.getMonth() + 1)).slice(-2);
        const d = ('0' + expires.getDate()).slice(-2);
        expiresPreview.value = `${y}-${m}-${d}`;
    }

    inspectionInput.addEventListener('input', calcExpire);
    calcExpire();
});
</script>
@endpush