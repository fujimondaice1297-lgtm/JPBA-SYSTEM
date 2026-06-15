@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1180px;">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2 class="mb-1">公式ランキング管理</h2>
            <div class="text-muted">
                年度末の公式ポイントランキングを確定保存し、翌年度シードプロ生成の正本にします。
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('pro_bowler_seed_lists.index') }}" class="btn btn-outline-primary">
                年度別シード管理へ
            </a>
            <a href="{{ route('tournament_pro.index') }}" class="btn btn-outline-secondary">
                今年度シードプロへ
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">入力内容を確認してください。</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $importSummary = session('official_ranking_import_summary');
    @endphp

    @if (is_array($importSummary))
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white fw-bold">直近の取込結果</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">対象</div>
                            <div class="fw-bold">
                                {{ $importSummary['ranking_year'] ?? '-' }}年
                                {{ $genderLabels[$importSummary['gender'] ?? ''] ?? ($importSummary['gender'] ?? '-') }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">取込行数</div>
                            <div class="fw-bold">{{ number_format((int) ($importSummary['row_count'] ?? 0)) }}件</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">選手マスタ照合済み</div>
                            <div class="fw-bold">{{ number_format((int) ($importSummary['matched_count'] ?? 0)) }}件</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">未照合</div>
                            <div class="fw-bold">{{ number_format((int) ($importSummary['unmatched_count'] ?? 0)) }}件</div>
                        </div>
                    </div>
                </div>

                @if (!empty($importSummary['unmatched_rows']))
                    <div class="alert alert-warning mt-3 mb-0">
                        <div class="fw-bold mb-2">未照合の行があります</div>
                        <div class="small mb-2">
                            ライセンス下4桁 + 性別で選手マスタに一致しなかった行です。必要に応じて選手マスタ側を確認してください。
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">順位</th>
                                        <th style="width: 120px;">No</th>
                                        <th>氏名</th>
                                        <th style="width: 120px;">ポイント</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($importSummary['unmatched_rows'] as $row)
                                        <tr>
                                            <td class="text-end">{{ $row['ranking_rank'] ?? '-' }}</td>
                                            <td class="text-end">{{ $row['license_digits'] ?? '-' }}</td>
                                            <td>{{ $row['name_kanji'] ?? '-' }}</td>
                                            <td class="text-end">{{ isset($row['points']) ? number_format((float) $row['points']) : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="alert alert-info">
        <div class="fw-bold mb-1">運用方針</div>
        <div>
            公式戦がすべて終了した時点の最終ポイントランキングをこの画面で確定保存します。<br>
            保存先は <code>pro_bowler_ranking_snapshots</code> / <code>pro_bowler_ranking_rows</code> です。
            翌年度のシードプロは、この確定ランキングから年度別シード管理で生成します。
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">公式PDFからランキングを取り込む</div>
        <div class="card-body">
            <form method="POST" action="{{ route('rankings.import_official') }}">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold">ランキング年度</label>
                        <input type="number" name="ranking_year" class="form-control" value="{{ old('ranking_year', $defaultRankingYear) }}" min="2000" max="2100" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">性別</label>
                        <select name="gender" class="form-select" required>
                            @foreach ($genderLabels as $genderValue => $genderLabel)
                                <option value="{{ $genderValue }}" @selected(old('gender', 'M') === $genderValue)>
                                    {{ $genderLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">確定日</label>
                        <input type="date" name="as_of_date" class="form-control" value="{{ old('as_of_date') }}">
                        <div class="form-text">PDFに記載された日付を入れます。</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">参照URL</label>
                        <input type="text" name="source_url" class="form-control" value="{{ old('source_url', $officialRankingUrls['M'] ?? '') }}">
                        <div class="form-text">
                            男子: {{ $officialRankingUrls['M'] ?? '-' }}<br>
                            女子: {{ $officialRankingUrls['F'] ?? '-' }}
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">備考</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="例: 2025年度公式最終ポイントランキングPDFから取込">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">ランキング本文貼り付け</label>
                    <textarea name="ranking_text" class="form-control" rows="14" required placeholder="PDFのランキング表をコピーして貼り付けてください。順位・ライセンスNo・氏名・期・TM数・G数・総T/PIN・AVG・ポイント・賞金額が含まれる行を読み取ります。">{{ old('ranking_text') }}</textarea>
                    <div class="form-text">
                        例: <code>1 1423 安里　秀策 59 (株)コロナワールド 13 248 54,669 220.43 3,366 3,005,800 ...</code><br>
                        同じ年度・性別の確定ランキングが既にある場合は、既存行を削除して差し替えます。
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        公式ランキングを確定保存する
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">保存済み公式ランキング</div>
        <div class="card-body p-0">
            @if ($snapshots->isEmpty())
                <div class="p-4 text-center text-muted">
                    公式ランキングはまだ保存されていません。
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">年度</th>
                                <th style="width: 100px;">性別</th>
                                <th style="width: 120px;">確定日</th>
                                <th style="width: 120px;">件数</th>
                                <th>参照URL / 備考</th>
                                <th style="width: 180px;">次の操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshots as $snapshot)
                                <tr>
                                    <td class="text-end">{{ $snapshot->ranking_year }}</td>
                                    <td>{{ $genderLabels[$snapshot->gender] ?? $snapshot->gender }}</td>
                                    <td>{{ optional($snapshot->as_of_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td class="text-end">{{ number_format((int) $snapshot->rows_count) }}件</td>
                                    <td>
                                        @if ($snapshot->source_url)
                                            <div>
                                                <a href="{{ $snapshot->source_url }}" target="_blank" rel="noopener">
                                                    {{ $snapshot->source_url }}
                                                </a>
                                            </div>
                                        @endif
                                        <div class="small text-muted">{{ $snapshot->notes ?: '-' }}</div>
                                    </td>
                                    <td>
                                        <a href="{{ route('pro_bowler_seed_lists.index') }}" class="btn btn-outline-primary btn-sm">
                                            シード生成へ
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
