@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">認定インストラクターCSV取込</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info">
        <div class="fw-bold mb-2">この取込で行うこと</div>
        <ul class="mb-0 ps-3">
            <li><code>AuthInstructor.csv</code> を <code>instructor_registry</code> に <code>source_type = auth_instructor_csv</code> / <code>instructor_category = certified</code> として登録・更新します。</li>
            <li>結線は <strong>license_no 一致を最優先</strong> に行い、一致しない場合のみ <strong>氏名 + 補助条件の一意一致</strong> で自動結線します。</li>
            <li>CSVに存在する current な認定行は、指定年度の <strong>更新済み</strong> として扱います。</li>
            <li>指定年度の CSV に未掲載だった current な認定行は、<strong>期限切れ</strong> として履歴化します。</li>
            <li>current な <code>pro_bowler</code> / <code>pro_instructor</code> が見つかった認定行は、認定 current にせず履歴行として更新します。</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('instructors.import_auth') }}" enctype="multipart/form-data" class="card shadow-sm">
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="csv" class="form-label">CSVファイル</label>
                    <input type="file" name="csv" id="csv" class="form-control" accept=".csv,.txt" required>
                </div>

                <div class="col-md-3">
                    <label for="renewal_year" class="form-label">更新対象年度</label>
                    <input
                        type="number"
                        name="renewal_year"
                        id="renewal_year"
                        class="form-control"
                        min="2000"
                        max="2099"
                        value="{{ old('renewal_year', $defaultRenewalYear ?? now()->year) }}"
                    >
                    <div class="form-text">更新期限は自動で <strong>12/31</strong> 扱いになります。</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">取込後の主な確認先</label>
                    <div class="d-grid gap-2">
                        <a href="{{ route('instructors.index', ['source_type' => 'auth_instructor_csv']) }}" class="btn btn-outline-secondary btn-sm">認定CSV一覧</a>
                        <a href="{{ route('instructors.index', ['instructor_class' => 'certified_instructor', 'unlinked_certified' => 1]) }}" class="btn btn-outline-warning btn-sm">未結線認定</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">取込実行</button>
            <a href="{{ route('instructors.index') }}" class="btn btn-secondary">戻る</a>
        </div>
    </form>
</div>
@endsection
