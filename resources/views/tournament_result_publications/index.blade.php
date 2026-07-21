@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">公式結果の確定・公開</h1>
            <div class="text-muted">{{ $tournament->year }}年 {{ $tournament->name }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('tournaments.result_snapshots.index', $tournament) }}" class="btn btn-outline-secondary">正式成績反映へ</a>
            <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-outline-secondary">成績一覧へ</a>
            @if($currentPublication)
                <a href="{{ route('tournaments.results.pdf', $tournament) }}" class="btn btn-primary" target="_blank" rel="noopener">公開PDFを確認</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-4">
        <h2 class="h5 mb-3">公開対象</h2>
        @if($finalSnapshots->isEmpty())
            <div class="alert alert-warning mb-0">最終成績スナップショットがありません。先に正式成績反映を行ってください。</div>
        @else
            <form method="GET" action="{{ route('tournaments.result_publications.index', $tournament) }}" class="row g-2 align-items-end">
                <div class="col-lg-8">
                    <label for="snapshot_id" class="form-label">最終成績スナップショット</label>
                    <select id="snapshot_id" name="snapshot_id" class="form-select">
                        @foreach($finalSnapshots as $snapshot)
                            <option value="{{ $snapshot->id }}" @selected($selectedSnapshot && (int) $selectedSnapshot->id === (int) $snapshot->id)>
                                #{{ $snapshot->id }} {{ $snapshot->result_name }} / {{ $snapshot->rows_count }}件 / {{ $snapshot->is_current ? '最新' : '旧版' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-outline-primary">プレビューを更新</button>
                </div>
            </form>
        @endif
    </section>

    @if($preview && $selectedSnapshot)
        <section class="mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">確定前プレビュー</h2>
                <span class="badge {{ $preview['can_publish'] ? 'text-bg-success' : 'text-bg-danger' }}">
                    {{ $preview['can_publish'] ? '確定可能' : '要修正' }}
                </span>
            </div>

            @if(!empty($preview['errors']))
                <div class="alert alert-danger">
                    <div class="fw-bold mb-1">確定できない項目</div>
                    <ul class="mb-0">
                        @foreach($preview['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($preview['warnings']))
                <div class="alert alert-warning">
                    <ul class="mb-0">
                        @foreach($preview['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-2"><div class="text-muted small">成績</div><div class="fw-bold">{{ number_format($preview['summary']['row_count']) }}件</div></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">プロ</div><div class="fw-bold">{{ number_format($preview['summary']['pro_count']) }}名</div></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">アマ</div><div class="fw-bold">{{ number_format($preview['summary']['amateur_count']) }}名</div></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">合計ポイント</div><div class="fw-bold">{{ number_format($preview['summary']['total_points']) }}P</div></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">賞金総額</div><div class="fw-bold">¥{{ number_format($preview['summary']['total_prize_money']) }}</div></div>
                <div class="col-6 col-lg-2"><div class="text-muted small">公開元</div><div class="fw-bold">{{ number_format($preview['summary']['source_snapshot_count']) }}表</div></div>
            </div>

            <div class="mb-3">
                <div class="small text-muted mb-1">公開PDFへ固定する成績表</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($preview['source_snapshots'] as $sourceSnapshot)
                        <span class="badge text-bg-light border">{{ $sourceSnapshot->result_name }} #{{ $sourceSnapshot->id }}</span>
                    @endforeach
                </div>
            </div>

            <div class="table-responsive mb-3" style="max-height: 34rem;">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-dark position-sticky top-0">
                        <tr>
                            <th>順位</th>
                            <th>選手</th>
                            <th>元成績</th>
                            <th class="text-end">G</th>
                            <th class="text-end">TP</th>
                            <th class="text-end">入賞P</th>
                            <th class="text-end">ステップP</th>
                            <th class="text-end">合計P</th>
                            <th class="text-end">賞金</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($preview['rows'] as $row)
                            <tr>
                                <td>{{ $row['ranking'] }}</td>
                                <td>
                                    <div>{{ $row['display_name'] }}</div>
                                    <div class="small text-muted">{{ $row['pro_bowler_license_no'] ?? 'アマ' }}</div>
                                </td>
                                <td class="small">{{ $row['source_result_code'] }}</td>
                                <td class="text-end">{{ number_format((int) ($row['games'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((int) ($row['total_pin'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((int) ($row['award_points'] ?? 0)) }}</td>
                                <td class="text-end">{{ number_format((int) ($row['step_points'] ?? 0)) }}</td>
                                <td class="text-end fw-bold">{{ number_format((int) ($row['points'] ?? 0)) }}</td>
                                <td class="text-end">¥{{ number_format((int) ($row['prize_money'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($preview['can_publish'])
                @if(auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('tournaments.result_publications.publish', $tournament) }}" onsubmit="return confirm('この内容を公式結果として確定し、ポイント・賞金・タイトル・公開PDFへ反映します。続行しますか？');">
                        @csrf
                        <input type="hidden" name="snapshot_id" value="{{ $selectedSnapshot->id }}">
                        <input type="hidden" name="expected_checksum" value="{{ $preview['result_checksum'] }}">
                        <div class="mb-3">
                            <label for="publication_notes" class="form-label">確定メモ</label>
                            <textarea id="publication_notes" name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes') }}</textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="confirm_publish" name="confirm_publish" required>
                            <label class="form-check-label" for="confirm_publish">順位、ポイント、賞金、タイトル対象、公開元成績表を確認しました</label>
                        </div>
                        <button type="submit" class="btn btn-danger">公式結果として確定する</button>
                    </form>
                @else
                    <div class="alert alert-info mb-0">内容を確認できます。公式結果の確定操作は管理者のみ実行できます。</div>
                @endif
            @endif
        </section>
    @endif

    <section>
        <h2 class="h5 mb-3">確定履歴</h2>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th>版</th>
                        <th>状態</th>
                        <th>最終成績</th>
                        <th>件数</th>
                        <th>ポイント</th>
                        <th>賞金総額</th>
                        <th>確定日時</th>
                        <th>確定者</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($publications as $publication)
                        <tr>
                            <td>第{{ $publication->revision }}版</td>
                            <td><span class="badge {{ $publication->status === 'current' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $publication->status === 'current' ? '公開中' : '旧版' }}</span></td>
                            <td>{{ $publication->snapshot?->result_name ?? '削除済み' }} #{{ $publication->snapshot_id }}</td>
                            <td>{{ number_format($publication->row_count) }}</td>
                            <td>{{ number_format($publication->total_points) }}P</td>
                            <td>¥{{ number_format($publication->total_prize_money) }}</td>
                            <td>{{ optional($publication->published_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $publication->publishedBy?->name ?? $publication->publishedBy?->email ?? 'system' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">新しい確定フローで公開した履歴はまだありません。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
