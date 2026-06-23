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
            <a href="{{ route('tournaments.seed_players.pdf', $tournament) }}" class="btn btn-info text-white" target="_blank" rel="noopener">優先出場者PDF</a>
            <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-outline-secondary">大会詳細へ戻る</a>
            <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-outline-secondary">大会成績一覧へ</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
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
            シード理由は、優先出場者PDF右側の各枠に対応しています。
            年度別シード一覧に登録済みの選手と、この画面で追加した大会別シードを合わせて、
            下の「大会優先出場者一覧」で確認できます。
            S表示はライセンスNoのDB値を変更せず、画面・成績表・PDF上だけで <strong>S 0524</strong> のように表示します。
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">シード選手を追加</div>
        <div class="card-body">
            <form method="POST" action="{{ route('tournaments.seed_players.store', $tournament) }}">
                @csrf

                <div class="row g-3 align-items-start">
                    <div class="col-md-3">
                        <label for="license_no" class="form-label">ライセンスNo</label>
                        <input
                            type="text"
                            id="license_no"
                            name="license_no"
                            value="{{ old('license_no') }}"
                            class="form-control @error('license_no') is-invalid @enderror"
                            placeholder="例：0524 / M00000524 / F00000524"
                            required
                        >
                        @error('license_no')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="seed_source_type" class="form-label">シード理由・PDF枠</label>
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
                        <div class="form-text">
                            PDF右側の②〜⑪枠へ自動振り分けします。TSは左側の①に表示します。<br>
                            ライセンスNoを4桁で入力した場合は、この大会の対象性別（男子/女子）を優先して照合します。
                        </div>
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
                            placeholder="通常は空欄でOK"
                        >
                        @error('display_label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-1">
                        <label class="form-label d-none d-md-block">&nbsp;</label>
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
                            placeholder="例：要項確認済み、対象大会名、推薦理由など"
                        >
                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">大会優先出場者一覧（自動生成）</div>
        <div class="card-body p-0">
            @if(empty($priorityPlayers))
                <div class="p-4 text-muted">
                    この大会の年度別シード、または大会別追加シードがまだありません。
                    年度別シード一覧を作成するか、この画面で大会別シードを追加してください。
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 priority-player-table">
                        <thead class="table-light">
                            <tr>
                                <th class="text-end">出場<br>優先</th>
                                <th class="text-end">元順位</th>
                                <th class="seed-license">ライセンスNo</th>
                                <th>氏名</th>
                                <th>フリガナ</th>
                                <th>シード種別</th>
                                <th>由来</th>
                                <th>備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($priorityPlayers as $player)
                                <tr>
                                    <td class="text-end fw-bold">{{ $player['priority_no'] }}</td>
                                    <td class="text-end">{{ $player['ranking_rank'] ?? '-' }}</td>
                                    <td class="seed-license">{{ $player['license_no'] ?? '-' }}</td>
                                    <td>{{ $player['name'] ?? '-' }}</td>
                                    <td class="small text-muted">{{ $player['kana'] ?? '' }}</td>
                                    <td>{{ $player['seed_label'] ?? '-' }}</td>
                                    <td class="small">{{ $player['source_label'] ?? '-' }}</td>
                                    <td class="small">{{ $player['note'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 small text-muted border-top">
                    年度別シードと大会別追加シードを合わせた確認用一覧です。
                    PDF右側の各枠は、登録時に選んだシード理由・PDF枠をもとに自動振り分けします。
                </div>
            @endif
        </div>
    </div>

    @php
        $seedPlayersBySourceType = $seedPlayers->groupBy('seed_source_type');
    @endphp
    <div class="card mb-4">
        <div class="card-header fw-bold">PDF枠別 登録状況</div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($seedSourceOptions as $sourceType => $sourceLabel)
                    @php
                        $playersInSection = $seedPlayersBySourceType->get($sourceType, collect());
                    @endphp
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="fw-semibold">{{ $sourceLabel }}</div>
                                <span @class(['badge', 'text-bg-secondary' => $playersInSection->isEmpty(), 'text-bg-primary' => $playersInSection->isNotEmpty()])>
                                    {{ $playersInSection->count() }}名
                                </span>
                            </div>

                            @if($playersInSection->isEmpty())
                                <div class="small text-muted">未登録</div>
                            @else
                                <ul class="list-unstyled mb-0 small">
                                    @foreach($playersInSection as $sectionPlayer)
                                        @php
                                            $sectionBowlerName = $sectionPlayer->bowler->name_kanji
                                                ?? $sectionPlayer->bowler->name
                                                ?? $sectionPlayer->bowler->display_name
                                                ?? '-';
                                            $sectionLicenseNo = $sectionPlayer->license_no ? mb_substr(strtoupper(trim($sectionPlayer->license_no)), -4) : '-';
                                        @endphp
                                        <li class="d-flex justify-content-between gap-2 py-1 border-top">
                                            <span>{{ $sectionBowlerName }}</span>
                                            <span class="text-muted text-nowrap">{{ $sectionLicenseNo }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="small text-muted mt-3">
                ここは大会別追加シードだけの登録状況です。年度別TSは上の「大会優先出場者一覧（自動生成）」とPDF左側①に反映されます。
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">現在の大会別シード</div>
        <div class="card-body p-0">
            @if($seedPlayers->isEmpty())
                <div class="p-4 text-muted">この大会に大会別追加シードはまだ登録されていません。</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 seed-player-table">
                        <thead class="table-light">
                            <tr>
                                <th class="text-end">優先</th>
                                <th>氏名</th>
                                <th class="seed-license">ライセンスNo</th>
                                <th>シード理由・PDF枠</th>
                                <th>表示ラベル</th>
                                <th>備考</th>
                                <th class="text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($seedPlayers as $seedPlayer)
                                @php
                                    $updateFormId = 'seed-player-update-' . $seedPlayer->id;
                                    $bowlerName = $seedPlayer->bowler->name_kanji
                                        ?? $seedPlayer->bowler->name
                                        ?? $seedPlayer->bowler->display_name
                                        ?? '-';
                                    $displayLicenseNo = $seedPlayer->license_no ? mb_substr(strtoupper(trim($seedPlayer->license_no)), -4) : '-';
                                    $isAlsoAnnualSeed = $annualSeedOverlapMap[$seedPlayer->id] ?? false;
                                @endphp
                                <tr @class(['table-warning' => $isAlsoAnnualSeed])>
                                    <td class="text-end seed-priority-cell">
                                        <form id="{{ $updateFormId }}" method="POST" action="{{ route('tournaments.seed_players.store', $tournament) }}">
                                            @csrf
                                            <input type="hidden" name="seed_player_id" value="{{ $seedPlayer->id }}">
                                        </form>
                                        <input
                                            type="number"
                                            name="priority_order"
                                            value="{{ $seedPlayer->priority_order }}"
                                            form="{{ $updateFormId }}"
                                            class="form-control form-control-sm text-end seed-priority-input"
                                            min="1"
                                            max="9999"
                                            placeholder="-"
                                        >
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $bowlerName }}</div>
                                        <div class="small text-muted">表示：{{ $displayLicenseNo }}</div>
                                        @if($isAlsoAnnualSeed)
                                            <div class="mt-1">
                                                <span class="badge text-bg-warning">年度別TSにも該当</span>
                                            </div>
                                            <div class="small text-muted mt-1">PDFでは左側①を優先表示</div>
                                        @endif
                                    </td>
                                    <td class="seed-license-edit">
                                        <input
                                            type="text"
                                            name="license_no"
                                            value="{{ $seedPlayer->license_no }}"
                                            form="{{ $updateFormId }}"
                                            class="form-control form-control-sm seed-license-input"
                                            required
                                        >
                                    </td>
                                    <td class="seed-source-edit">
                                        <select
                                            name="seed_source_type"
                                            form="{{ $updateFormId }}"
                                            class="form-select form-select-sm seed-source-select"
                                            required
                                        >
                                            @foreach($seedSourceOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($seedPlayer->seed_source_type === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="seed-label-edit">
                                        <input
                                            type="text"
                                            name="display_label"
                                            value="{{ $seedPlayer->display_label }}"
                                            form="{{ $updateFormId }}"
                                            class="form-control form-control-sm seed-label-input"
                                            placeholder="空欄でOK"
                                        >
                                    </td>
                                    <td class="seed-note-edit">
                                        <input
                                            type="text"
                                            name="note"
                                            value="{{ $seedPlayer->note }}"
                                            form="{{ $updateFormId }}"
                                            class="form-control form-control-sm seed-note-input"
                                            placeholder="備考"
                                        >
                                    </td>
                                    <td class="text-center seed-action-cell">
                                        <div class="d-flex flex-wrap justify-content-center gap-1">
                                            <button type="submit" form="{{ $updateFormId }}" class="btn btn-sm btn-primary">保存</button>
                                            <form
                                                method="POST"
                                                action="{{ route('tournaments.seed_players.destroy', [$tournament, $seedPlayer]) }}"
                                                onsubmit="return confirm('このシード設定を解除しますか？');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">解除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 small text-muted border-top">
                    行内の内容を修正して「保存」を押すと、大会別追加シードをその場で更新できます。
                    同一大会内で同じ選手を重複登録しないようにしています。
                    ライセンスNoを4桁で入力した場合は、この大会の対象性別（男子/女子）を優先して照合します。
                    年度別TSにも該当する選手は、PDFでは左側①のトーナメントシード枠を優先表示します。
                    ライセンスNoのDB値は保持しつつ、一覧やPDFでは下4桁表示を基本にします。
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .seed-player-table th,
    .seed-player-table td,
    .priority-player-table th,
    .priority-player-table td {
        white-space: nowrap;
    }

    .seed-player-table .seed-license,
    .priority-player-table .seed-license {
        width: 7.5rem;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .priority-player-table th,
    .priority-player-table td {
        vertical-align: middle;
    }

    .seed-player-table td {
        vertical-align: middle;
    }

    .seed-priority-cell {
        width: 5rem;
    }

    .seed-priority-input {
        min-width: 4.5rem;
    }

    .seed-license-edit {
        width: 8.5rem;
    }

    .seed-license-input {
        min-width: 8rem;
        font-variant-numeric: tabular-nums;
    }

    .seed-source-edit {
        min-width: 17rem;
    }

    .seed-source-select {
        min-width: 16.5rem;
    }

    .seed-label-edit {
        width: 9rem;
    }

    .seed-label-input {
        min-width: 8.5rem;
    }

    .seed-note-edit {
        min-width: 13rem;
    }

    .seed-note-input {
        min-width: 12.5rem;
    }

    .seed-action-cell {
        width: 7.5rem;
    }
</style>
@endsection
