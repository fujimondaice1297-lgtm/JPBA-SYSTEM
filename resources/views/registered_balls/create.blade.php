@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">ボール登録フォーム</h2>

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

    {{-- 登録フォーム --}}
    <form method="POST" action="{{ route('registered_balls.store') }}">
        @csrf

        {{-- プロボウラー（Select2 検索） --}}
        <div class="mb-3">
            <label for="license_no" class="form-label">プロボウラー</label>
            <select id="license_no" name="license_no" class="form-control" required>
                <option value="">プロボウラーを検索してください</option>
            </select>
        </div>

        {{-- メーカー／発売年 → 承認ボール --}}
        <div class="mb-3">
            <label for="manufacturer_select">メーカー</label>
            <select id="manufacturer_select" class="form-control">
                <option value="">選択してください</option>
                @foreach ($manufacturers as $m)
                    <option value="{{ $m }}">{{ $m }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="release_year_select">発売年</label>
            <select id="release_year_select" class="form-control">
                <option value="">選択してください</option>
                @for ($year = date('Y'); $year >= 2000; $year--)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endfor
            </select>
        </div>

        <div class="mb-3">
            <label for="approved_ball_select">承認ボール</label>
            <select name="approved_ball_id" id="approved_ball_select" class="form-control" required>
                <option value="">選択してください</option>
            </select>
        </div>

        {{-- シリアルナンバー --}}
        <div class="mb-3">
            <label for="serial_number" class="form-label">シリアルナンバー</label>
            <input type="text" name="serial_number" id="serial_number" class="form-control"
                   value="{{ old('serial_number') }}" required>
        </div>

        {{-- 登録日 --}}
        <div class="mb-3">
            <label for="registered_at" class="form-label">登録日</label>
            <input type="date" name="registered_at" id="registered_at" class="form-control"
                   value="{{ old('registered_at') ?? now()->format('Y-m-d') }}" required>
        </div>

        {{-- 検量証番号（← 送信名を inspection_number に統一） --}}
        <div class="mb-3">
            <label for="inspection_number" class="form-label">検量証番号</label>
            <input type="text" name="inspection_number" id="inspection_number" class="form-control"
                   value="{{ old('inspection_number') }}">
        </div>

        {{-- 有効期限（表示用。検量証番号がある時だけ自動計算／readonly） --}}
        <div class="mb-3" id="expires_group" style="display:none;">
            <label for="expires_at">有効期限</label>
            <input type="date" name="expires_at" id="expires_at" class="form-control"
                   value="{{ old('expires_at') }}" readonly>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">登録</button>
            <a href="{{ route('registered_balls.index') }}" class="btn btn-secondary">戻る</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(function () {
    // プロボウラー検索（Select2）
    $('#license_no').select2({
        placeholder: 'プロボウラーを検索してください',
        ajax: {
            url: '/api/pro_bowlers',
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term }),
            processResults: data => ({
                results: data.map(item => ({
                    id: item.license_no,
                    text: `${item.license_no} - ${item.name_kanji}`
                }))
            }),
            cache: true
        },
        minimumInputLength: 1
    });

    // メーカー×発売年 → 承認ボール一覧
    const manufacturerSelect = document.getElementById('manufacturer_select');
    const yearSelect = document.getElementById('release_year_select');
    const approvedBallSelect = document.getElementById('approved_ball_select');

    function fetchApproved() {
        const m = manufacturerSelect.value;
        const y = yearSelect.value;
        if (!m || !y) return;

        fetch(`/api/approved-balls/filter?manufacturer=${encodeURIComponent(m)}&release_year=${encodeURIComponent(y)}`)
            .then(r => r.json())
            .then(data => {
                approvedBallSelect.innerHTML = '<option value="">選択してください</option>';
                data.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = `${b.name}（${b.manufacturer} / ${b.release_year}）`;
                    approvedBallSelect.appendChild(opt);
                });
            })
            .catch(err => console.error('ボール取得エラー', err));
    }
    manufacturerSelect.addEventListener('change', fetchApproved);
    yearSelect.addEventListener('change', fetchApproved);

    // 検量証番号が入ったら有効期限を自動計算して表示
    const insp = document.getElementById('inspection_number');
    const reg  = document.getElementById('registered_at');
    const exp  = document.getElementById('expires_at');
    const grp  = document.getElementById('expires_group');

    function calcExpire() {
        const has = insp.value.trim() !== '';
        grp.style.display = has ? 'block' : 'none';
        if (!has) { exp.value = ''; return; }

        // reg + 1年 - 1日
        const d = new Date(reg.value);
        if (isNaN(d)) { exp.value = ''; return; }
        const e = new Date(d); e.setFullYear(e.getFullYear() + 1); e.setDate(e.getDate() - 1);
        const y = e.getFullYear();
        const m = ('0' + (e.getMonth()+1)).slice(-2);
        const dd= ('0' + e.getDate()).slice(-2);
        exp.value = `${y}-${m}-${dd}`;
    }
    insp.addEventListener('input', calcExpire);
    reg.addEventListener('change', calcExpire);
    calcExpire(); // 初期表示
});
</script>
@endpush
