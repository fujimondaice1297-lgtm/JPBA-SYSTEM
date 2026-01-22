@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">記録編集</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('record_types.update', $recordType->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header font-weight-bold">公開情報（HPに表示）</div>
            <div class="card-body">

                <div class="form-group">
                    <label for="record_type">記録種別（※必須）</label>
                    <select name="record_type" id="record_type" class="form-control" required>
                        <option value="">選択してください</option>
                        <option value="perfect" {{ old('record_type', $recordType->record_type) == 'perfect' ? 'selected' : '' }}>パーフェクト</option>
                        <option value="seven_ten" {{ old('record_type', $recordType->record_type) == 'seven_ten' ? 'selected' : '' }}>7-10スプリットメイド</option>
                        <option value="eight_hundred" {{ old('record_type', $recordType->record_type) == 'eight_hundred' ? 'selected' : '' }}>800シリーズ</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pro_bowler_id">選手 <span class="text-danger">※必須</span></label>
                    <select name="pro_bowler_id" id="pro_bowler_id" class="form-control" required>
                        <option value="">選択してください</option>
                        @foreach ($proBowlers as $bowler)
                            <option value="{{ $bowler->id }}" {{ old('pro_bowler_id', $recordType->pro_bowler_id) == $bowler->id ? 'selected' : '' }}>
                                {{ $bowler->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="tournament_name">大会名 <span class="text-danger">※必須</span></label>
                    <input type="text" name="tournament_name" class="form-control" value="{{ old('tournament_name', $recordType->tournament_name) }}" required>
                </div>

                <div class="form-group">
                    <label for="game_numbers">該当ゲーム数 <span class="text-danger">※必須</span></label>
                    <input type="text" name="game_numbers" class="form-control" value="{{ old('game_numbers', $recordType->game_numbers) }}" required>
                </div>

                <div class="form-group">
                    <label for="frame_number">フレーム番号</label>
                    <input type="text" name="frame_number" class="form-control" value="{{ old('frame_number', $recordType->frame_number) }}">
                </div>

                <div class="form-group">
                    <label for="awarded_on">達成日 <span class="text-danger">※必須</span></label>
                    <input type="date" name="awarded_on" class="form-control" value="{{ old('awarded_on', $recordType->awarded_on) }}" required>
                </div>

                <div class="form-group">
                    <label for="certification_number">公認番号 <span class="text-danger">※必須</span></label>
                    <input type="text" name="certification_number" class="form-control" value="{{ old('certification_number', $recordType->certification_number) }}" required>
                </div>

            </div>
        </div>

        <div class="text-right">
            <button type="submit" class="btn btn-primary">更新</button>
            <a href="{{ route('record_types.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
    <hr class="my-4">
        @if(auth()->user()?->isAdmin())
        <form action="{{ route('admin.record_types.destroy', $recordType->id) }}"
                method="POST"
                onsubmit="return confirm('本当にこの記録を削除しますか？');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">削除</button>
        </form>
        @endif
</div>
@endsection
