{{-- resources/views/tournament_seed_players/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">大会別シード設定</h1>
            <div class="text-muted">{{ $tournament->year ?? '' }}年 {{ $tournament->name }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-outline-secondary">大会詳細へ戻る</a>
            <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-outline-secondary">大会成績一覧へ</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">入力内容を確認してください。</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info small">
        <div class="fw-bold">この画面の役割</div>
        <div>
            この大会だけでシード扱いにする選手を設定します。
            ここで登録した選手は、成績表・速報・PDFのライセンスNo欄で <strong>S 0524</strong> のように表示されます。
            ライセンスNoのDB値そのものは変更しません。
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">シード選手を追加</div>
        <div class="card-body">
            <form method="POST" action="{{ route('tournaments.seed_players.store', $tournament) }}">
                @csrf

                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="license_no" class="form-label">ライセンスNo</label>
                        <input
                            type="text"
                            id="license_no"
                            name="license_no"
                            value="{{ old('license_no') }}"
                            class="form-control @error('license_no') is-invalid @enderror"
                            placeholder="例：0524 / F00000524"
                            required
                        >
                        @error('license_no')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="seed_source_type" class="form-label">シード理由</label>
                        <select
                            id="seed_source_type"
                            name="seed_source_type"
                            class="form-select @error('seed_source_type') is-invalid @enderror"
                            required
                        >
                            @foreach($seedSourceOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('seed_source_type', 'manual') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('seed_source_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="priority_order" class="form-label">優先順位</label>
                        <input
                            type="number"
                            id="priority_order"
                            name="priority_order"
                            value="{{ old('priority_order') }}"
                            class="form-control @error('priority_order') is-invalid @enderror"
                            min="1"
                            max="9999"
                            placeholder="例：1"
                        >
                        @error('priority_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="display_label" class="form-label">表示ラベル</label>
                        <input
                            type="text"
                            id="display_label"
                            name="display_label"
                            value="{{ old('display_label') }}"
                            class="form-control @error('display_label') is-invalid @enderror"
                            placeholder="未入力可"
                        >
                        @error('display_label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">追加</button>
                    </div>

                    <div class="col-12">
                        <label for="note" class="form-label">備考</label>
                        <input
                            type="text"
                            id="note"
                            name="note"
                            value="{{ old('note') }}"
                            class="form-control @error('note') is-invalid @enderror"
                            placeholder="例：歴代優勝者枠、要項確認済み など"
                        >
                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">現在の大会別シード</div>
        <div class="card-body p-0">
            @if($seedPlayers->isEmpty())
                <div class="p-4 text-muted">この大会にシード選手はまだ登録されていません。</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 seed-player-table">
                        <thead class="table-light">
                            <tr>
                                <th class="text-end">優先</th>
                                <th>氏名</th>
                                <th class="seed-license">ライセンスNo</th>
                                <th>シード理由</th>
                                <th>表示ラベル</th>
                                <th>備考</th>
                                <th class="text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($seedPlayers as $seedPlayer)
                                @php
                                    $sourceLabel = $seedSourceOptions[$seedPlayer->seed_source_type] ?? $seedPlayer->seed_source_type;
                                    $bowlerName = $seedPlayer->bowler->name_kanji
                                        ?? $seedPlayer->bowler->name
                                        ?? $seedPlayer->bowler->display_name
                                        ?? '-';
                                @endphp
                                <tr>
                                    <td class="text-end">{{ $seedPlayer->priority_order ?? '-' }}</td>
                                    <td>{{ $bowlerName }}</td>
                                    <td class="seed-license">{{ $seedPlayer->license_no ?? '-' }}</td>
                                    <td>{{ $sourceLabel }}</td>
                                    <td>{{ $seedPlayer->display_label ?? '-' }}</td>
                                    <td class="small">{{ $seedPlayer->note ?? '-' }}</td>
                                    <td class="text-center">
                                        <form
                                            method="POST"
                                            action="{{ route('tournaments.seed_players.destroy', [$tournament, $seedPlayer]) }}"
                                            onsubmit="return confirm('このシード設定を解除しますか？');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">解除</button>
                                        </form>
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

<style>
    .seed-player-table th,
    .seed-player-table td {
        white-space: nowrap;
    }

    .seed-player-table .seed-license {
        width: 6.5rem;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
</style>
@endsection
