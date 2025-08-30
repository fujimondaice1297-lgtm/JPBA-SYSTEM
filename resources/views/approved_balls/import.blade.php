@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <h2>承認ボールCSVインポート</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('approved_balls.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label for="csv_file" class="form-label">CSVファイルを選択</label>
            <input type="file" class="form-control" id="csv_file" name="csv_file" required>
        </div>
        <button type="submit" class="btn btn-primary">インポート開始</button>
    </form>
</div>
@endsection
