@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1">公認記録</h2>
            <p class="text-muted mb-0">自動検出候補の確認と、公認パーフェクト・800シリーズ・7－10メイドの明細管理</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('record_types.series_definitions.index') }}" class="btn btn-outline-primary">800シリーズ設定</a>
            <a href="{{ route('record_types.sequences.index') }}" class="btn btn-outline-primary">公認番号設定</a>
            <a href="{{ route('record_types.create') }}" class="btn btn-success">手動登録</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('record_types.index') }}" class="card card-body mb-4">
        <div class="row g-2">
            <div class="col-lg-3">
                <label class="form-label">選手名・ライセンス番号</label>
                <input type="text" name="player_identifier" value="{{ request('player_identifier') }}" class="form-control">
            </div>
            <div class="col-lg-2">
                <label class="form-label">記録種別</label>
                <select name="record_type" class="form-select">
                    <option value="">すべて</option>
                    <option value="perfect" @selected(request('record_type') === 'perfect')>公認パーフェクト</option>
                    <option value="eight_hundred" @selected(request('record_type') === 'eight_hundred')>公認800シリーズ</option>
                    <option value="seven_ten" @selected(request('record_type') === 'seven_ten')>公認7－10メイド</option>
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label">状態</label>
                <select name="status" class="form-select">
                    <option value="">すべて</option>
                    <option value="candidate" @selected(request('status') === 'candidate')>確認待ち</option>
                    <option value="confirmed" @selected(request('status') === 'confirmed')>確認済み</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>却下</option>
                    <option value="void" @selected(request('status') === 'void')>無効</option>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">大会名</label>
                <input type="text" name="tournament_name" value="{{ request('tournament_name') }}" class="form-control">
            </div>
            <div class="col-lg-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">検索</button>
                <a href="{{ route('record_types.index') }}" class="btn btn-outline-secondary">解除</a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>状態</th>
                    <th>選手</th>
                    <th>種別</th>
                    <th>達成日・大会</th>
                    <th>ゲーム／シリーズ／フレーム</th>
                    <th>公認番号</th>
                    <th>取得元</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr @class(['table-warning' => $record->status === 'candidate'])>
                        <td>
                            <span @class([
                                'badge',
                                'bg-warning text-dark' => $record->status === 'candidate',
                                'bg-success' => $record->status === 'confirmed',
                                'bg-secondary' => in_array($record->status, ['rejected', 'void'], true),
                            ])>{{ $record->status_label }}</span>
                            @if ($record->warning)
                                <div class="small text-danger mt-1">{{ $record->warning }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $record->proBowler->name_kanji ?? '不明' }}</div>
                            <small class="text-muted">{{ $record->proBowler->license_no ?? '' }}</small>
                        </td>
                        <td>{{ $record->record_type_label }}</td>
                        <td>
                            <div>{{ optional($record->awarded_on)->format('Y/m/d') ?: '日付未確認' }}</div>
                            <div>{{ $record->tournament_name }}</div>
                            @if ($record->stage)<small class="text-muted">{{ $record->stage }}{{ $record->shift ? ' / '.$record->shift : '' }}</small>@endif
                        </td>
                        <td>
                            @if ($record->record_type === 'eight_hundred')
                                {{ $record->series_label ?: $record->game_numbers ?: '未確認' }}
                                @if ($record->series_total !== null)<div class="small">合計 {{ $record->series_total }}</div>@endif
                            @else
                                {{ $record->game_numbers ?: '未確認' }}
                                @if ($record->frame_number)<div class="small">{{ $record->frame_number }}</div>@endif
                            @endif
                        </td>
                        <td>{{ $record->certification_number ?: '未設定' }}</td>
                        <td>
                            {{ $record->source_label ?: $record->source_type ?: '手動' }}
                            @if ($record->source_url)
                                <div><a href="{{ $record->source_url }}" target="_blank" rel="noopener">根拠を開く</a></div>
                            @endif
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('record_types.edit', $record) }}" class="btn btn-sm btn-primary">確認・編集</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">該当する公認記録はありません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $records->links() }}
</div>
@endsection
