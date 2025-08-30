@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">記録登録</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($proBowlers as $bowler)
                    <option value="{{ $bowler->id }}">{{ $bowler->display_name }}</option>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('record_types.store') }}" method="POST">
        @csrf

        <div class="card mb-4">
            <div class="card-header font-weight-bold">公開情報（HPに表示）</div>
            <div class="card-body">

                <div class="form-group">
                    <label for="record_type">記録種別（※必須）</label>
                    <select name="record_type" id="record_type" class="form-control" required>
                        <option value="">選択してください</option>
                        <option value="perfect" {{ old('record_type') == 'perfect' ? 'selected' : '' }}>パーフェクト</option>
                        <option value="seven_ten" {{ old('record_type') == 'seven_ten' ? 'selected' : '' }}>7-10スプリットメイド</option>
                        <option value="eight_hundred" {{ old('record_type') == 'eight_hundred' ? 'selected' : '' }}>800シリーズ</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pro_bowler_license_no">ライセンス番号 <span class="text-danger">※必須</span></label>
                    <input type="text" id="pro_bowler_license_no" name="pro_bowler_license_no" class="form-control" placeholder="例: M0001234" required>
                    <input type="hidden" id="pro_bowler_id" name="pro_bowler_id">
                    <small id="bowler_name_display" class="form-text text-muted"></small>
                </div>

                <div class="form-group">
                    <label for="tournament_name">大会名 <span class="text-danger">※必須</span></label>
                    <input type="text" name="tournament_name" class="form-control" placeholder="例：全日本選手権" required>
                </div>

                <div class="form-group">
                    <label for="game_numbers">該当ゲーム数 <span class="text-danger">※必須</span></label>
                    <input type="text" name="game_numbers" class="form-control" placeholder="例：1や1,2,3など" required>
                </div>

                <div class="form-group">
                    <label for="frame_number">フレーム番号</label>
                    <input type="text" name="frame_number" class="form-control" placeholder="（任意）">
                </div>

                <div class="form-group">
                    <label for="awarded_on">達成日 <span class="text-danger">※必須</span></label>
                    <input type="date" name="awarded_on" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="certification_number">公認番号 <span class="text-danger">※必須</span></label>
                    <input type="text" name="certification_number" class="form-control" required>
                </div>

            </div>
        </div>

        <div class="text-right">
            <button type="submit" class="btn btn-primary">登録</button>
            <a href="{{ route('record_types.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('pro_bowler_license_no');
    const hiddenId = document.getElementById('pro_bowler_id');
    const nameDisplay = document.getElementById('bowler_name_display');

    input.addEventListener('blur', function () {
        const licenseNo = input.value.trim();
        if (!licenseNo) return;

        fetch(`/api/pro-bowler-by-license/${licenseNo}`)
            .then(response => {
                if (!response.ok) throw new Error('該当選手が見つかりません');
                return response.json();
            })
            .then(data => {
                hiddenId.value = data.id;
                nameDisplay.textContent = `選手名：${data.name_kanji}（${data.license_no}）`;
            })
            .catch(error => {
                hiddenId.value = '';
                nameDisplay.textContent = '該当する選手が見つかりません';
            });
    });
});
</script>

