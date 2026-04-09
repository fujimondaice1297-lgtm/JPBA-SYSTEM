@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="fw-bold mb-4">プロボウラーCSVインポート</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-secondary">
        <div class="fw-bold mb-2">この取込で行うこと</div>
        <ul class="mb-0">
            <li><code>Pro_colum.csv</code> を <code>pro_bowlers</code> に登録・更新します。</li>
            <li>同時に <code>instructor_registry</code> の <code>source_type = pro_bowler_csv</code> を同期します。</li>
            <li>ティーチングプロは <code>member_class = pro_instructor</code> 基準で扱います。</li>
            <li>プロ系資格対象外になった行は、認定 current があれば認定復帰、なければ <code>qualification_removed</code> として履歴化します。</li>
            <li>取り込み後はトップ画面に件数サマリを表示します。</li>
        </ul>
    </div>

    <form action="{{ route('pro_bowlers.import') }}" method="POST" enctype="multipart/form-data" class="card shadow-sm">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label for="csv" class="form-label fw-bold">CSVファイル</label>
                <input type="file" name="csv" id="csv" class="form-control" accept=".csv,.txt" required>
                <div class="form-text">Shift-JIS / CP932 の CSV でも取り込めます。</div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-success">取り込み</button>
                <a href="{{ route('pro_bowlers.index') }}" class="btn btn-secondary">戻る</a>
                <a href="{{ route('pro_bowlers.list') }}" class="btn btn-outline-primary">全プロデータへ</a>
                <a href="{{ route('instructors.index', ['source_type' => 'pro_bowler_csv']) }}" class="btn btn-outline-dark">インストラクター一覧（プロCSV）へ</a>
            </div>
        </div>
    </form>
</div>
@endsection
