@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">登録ボール 編集</h2>

    {{-- エラーメッセージ --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>入力内容に誤りがあります：</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('registered_balls.update', $registeredBall->id) }}">
        @csrf
        @method('PUT')

        {{-- プロボウラー --}}
        <div class="mb-3">
            <label for="license_no" class="form-label">プロボウラー</label>
            <select name="license_no" class="form-control" required>
                <option value="">選択してください</option>
                @foreach($proBowlers as $bowler)
                    <option value="{{ $bowler->license_no }}"
                        {{ old('license_no', $registeredBall->license_no) == $bowler->license_no ? 'selected' : '' }}>
                        {{ $bowler->license_no }} - {{ $bowler->name_kanji }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- 承認ボール --}}
        <div class="mb-3">
            <label for="approved_ball_id" class="form-label">承認ボール</label>
            <select name="approved_ball_id" class="form-control" required>
                <option value="">選択してください</option>
                @foreach($approvedBalls as $ball)
                    <option value="{{ $ball->id }}"
                        {{ old('approved_ball_id', $registeredBall->approved_ball_id) == $ball->id ? 'selected' : '' }}>
                        {{ $ball->manufacturer }} - {{ $ball->name }}（{{ $ball->release_year }}年）
                    </option>
                @endforeach
            </select>
        </div>

        {{-- シリアルナンバー --}}
        <div class="mb-3">
            <label for="serial_number" class="form-label">シリアルナンバー</label>
            <input type="text" name="serial_number" class="form-control"
                   value="{{ old('serial_number', $registeredBall->serial_number) }}" required>
        </div>

        {{-- 登録日 --}}
        <div class="mb-3">
            <label for="registered_at" class="form-label">登録日</label>
            <input type="date" name="registered_at" id="registered_at" class="form-control"
                   value="{{ old('registered_at', optional($registeredBall->registered_at)->format('Y-m-d')) }}" required>
        </div>

        {{-- 検量証番号（← inspection_number に統一） --}}
        <div class="mb-3">
            <label for="inspection_number" class="form-label">検量証番号</label>
            <input type="text" name="inspection_number" id="inspection_number" class="form-control"
                   value="{{ old('inspection_number', $registeredBall->inspection_number) }}">
        </div>

        {{-- 有効期限（表示専用。検量証がある時のみ） --}}
        <div class="mb-3" id="expires_group" style="{{ $registeredBall->inspection_number ? '' : 'display:none;' }}">
            <label for="expires_at">有効期限</label>
            <input type="date" name="expires_at" id="expires_at" class="form-control"
                   value="{{ old('expires_at', optional($registeredBall->expires_at)->format('Y-m-d')) }}" readonly>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">更新</button>
            <a href="{{ route('registered_balls.index') }}" class="btn btn-secondary">戻る</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const insp = document.getElementById('inspection_number');
    const reg  = document.getElementById('registered_at');
    const exp  = document.getElementById('expires_at');
    const grp  = document.getElementById('expires_group');

    function calcExpire() {
        const has = insp.value.trim() !== '';
        grp.style.display = has ? 'block' : 'none';
        if (!has) { if (exp) exp.value = ''; return; }

        const d = new Date(reg.value);
        if (isNaN(d)) { if (exp) exp.value = ''; return; }
        const e = new Date(d); e.setFullYear(e.getFullYear() + 1); e.setDate(e.getDate() - 1);
        const y = e.getFullYear();
        const m = ('0' + (e.getMonth()+1)).slice(-2);
        const dd= ('0' + e.getDate()).slice(-2);
        if (exp) exp.value = `${y}-${m}-${dd}`;
    }

    if (insp) insp.addEventListener('input', calcExpire);
    if (reg)  reg.addEventListener('change', calcExpire);
});
</script>
@endpush
