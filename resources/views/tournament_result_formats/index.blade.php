@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="mb-1">最終成績フォーマット</h2>
            <div class="text-muted">
                年度ごとに変わる表示内容をExcelで編集し、新しい版として登録します。作成済みの大会は選択した版を固定して使用します。
            </div>
        </div>
        <a href="{{ route('tournaments.index') }}" class="btn btn-outline-secondary">大会一覧へ戻る</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

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
        <strong>編集するのはExcelの「年度設定」シートにある黄色欄です。</strong>
        大会名、英文名、主催・協賛・後援、開催日、会場、放映など、その年度だけ変更したい項目を入力してください。
        空欄は大会作成画面の登録値を使用します。
        順位・選手・ライセンス番号・スコア・ポイント・賞金・優勝者写真は確定データから自動反映するため、Excelでの入力・修正は不要です。
        シート名と名前付き範囲は削除・変更しないでください。
    </div>

    @foreach ($formats as $format)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>{{ $format->name }}</strong>
                    <span class="badge bg-secondary ms-2">{{ $format->code }}</span>
                </div>
                <span class="text-muted small">{{ $format->description }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>版</th>
                                <th>登録日時</th>
                                <th>備考</th>
                                <th>使用大会数</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($format->versions as $version)
                                <tr>
                                    <td>v{{ $version->version_no }}</td>
                                    <td>{{ $version->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $version->notes ?: '-' }}</td>
                                    <td>{{ number_format($version->tournaments()->count()) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('tournament_result_format_versions.download', $version) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            Excelダウンロード
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form method="POST"
                      action="{{ route('tournament_result_formats.versions.store', $format) }}"
                      enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label">年度設定を編集したExcel（新しい版として登録）</label>
                        <input type="file" name="template" accept=".xlsx" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">変更内容</label>
                        <input type="text" name="notes" class="form-control" placeholder="例：2027年度の主催・会場・放映情報へ更新">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">新しい版を登録</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <div class="card border-secondary">
        <div class="card-header"><strong>新しい形式を追加</strong></div>
        <div class="card-body">
            <form method="POST"
                  action="{{ route('tournament_result_formats.store') }}"
                  enctype="multipart/form-data"
                  class="row g-3">
                @csrf
                <div class="col-md-5">
                    <label class="form-label">表示名</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">形式コード</label>
                    <input type="text" name="code" class="form-control" placeholder="example_format" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">初版Excel</label>
                    <input type="file" name="template" accept=".xlsx" class="form-control" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">説明</label>
                    <input type="text" name="description" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">初版の備考</label>
                    <input type="text" name="notes" class="form-control">
                </div>
                <div class="col-12">
                    <button class="btn btn-outline-primary">形式を追加</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
