@extends('layouts.app')

@section('content')
<div class="container">
    <h2>使用ボール 編集フォーム</h2>

    <form method="POST" action="{{ route('used_balls.update', $usedBall->id) }}">
        @csrf
        @method('PATCH')

        <div class="mb-3">
            <label>使用ボール名</label>
            <select name="approved_ball_id" class="form-control" required>
                @foreach($balls as $ball)
                    <option value="{{ $ball->id }}" {{ $usedBall->approved_ball_id == $ball->id ? 'selected' : '' }}>
                        {{ $ball->manufacturer }} - {{ $ball->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label>シリアルナンバー</label>
            <input type="text" name="serial_number" class="form-control" value="{{ $usedBall->serial_number }}">
        </div>

        <div class="mb-3">
            <label>検量証番号</label>
            <input type="text" name="inspection_number" class="form-control" value="{{ $usedBall->inspection_number }}">
        </div>

        <div class="mb-3">
            <label>登録日</label>
            <input type="date" name="registered_at" class="form-control" value="{{ $usedBall->registered_at }}">
        </div>

        <button type="submit" class="btn btn-primary">更新する</button>
    </form>
</div>
@endsection