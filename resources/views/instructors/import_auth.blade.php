@extends('layouts.app')

@section('content')
<div class="container">
    <h1>認定インストラクターCSV取込</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul style="margin-bottom:0;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p>
        AuthInstructor.csv を取り込み、<code>instructor_registry</code> に
        <code>source_type = auth_instructor_csv</code> /
        <code>instructor_category = certified</code> として登録・更新します。<br>
        この取込では、名前一致だけで <code>pro_bowlers</code> へ自動結線しません。
    </p>

    <form method="POST" action="{{ route('instructors.import_auth') }}" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label for="csv" class="form-label">CSVファイル</label>
            <input type="file" name="csv" id="csv" class="form-control" accept=".csv,.txt" required>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">取込実行</button>
            <a href="{{ route('instructors.index') }}" class="btn btn-secondary">戻る</a>
        </div>
    </form>
</div>
@endsection
