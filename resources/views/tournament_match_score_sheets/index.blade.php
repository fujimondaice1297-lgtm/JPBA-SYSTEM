@extends('layouts.app')

@section('content')
<div class="container py-4">
    @php
        $editingSheet = $editingSheet ?? null;
        $formAction = $editingSheet
            ? route('tournaments.match_score_sheets.update', [$tournament, $editingSheet])
            : route('tournaments.match_score_sheets.store', $tournament);

        $defaultPlayers = collect(range(0, 3))->map(function ($i) use ($editingSheet) {
            $player = $editingSheet?->players?->get($i);

            return [
                'player_slot' => $player?->player_slot ?? chr(65 + $i),
                'pro_bowler_id' => $player?->pro_bowler_id,
                'pro_bowler_license_no' => $player?->pro_bowler_license_no,
                'display_name' => $player?->display_name,
                'name_kana' => $player?->name_kana,
                'dominant_arm' => $player?->dominant_arm,
                'lane_label' => $player?->lane_label,
                'final_score' => $player?->final_score,
                'is_winner' => $player?->is_winner,
                'frames' => $player
                    ? $player->frames->keyBy('frame_no')->map(fn ($frame) => [
                        'throw1' => $frame->throw1,
                        'throw2' => $frame->throw2,
                        'throw3' => $frame->throw3,
                        'cumulative_score' => $frame->cumulative_score,
                    ])->all()
                    : [],
            ];
        });
    @endphp

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $tournament->year }}年 {{ $tournament->name }}：スコアシート入力</h1>
            <p class="text-muted mb-0">シュートアウト・優勝決定戦など、PDFに載せる1Gスコア表を入力します。</p>
        </div>
        <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-outline-secondary">大会成績へ戻る</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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

    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            {{ $editingSheet ? 'スコアシート編集' : 'スコアシート新規登録' }}
        </div>
        <div class="card-body">
            <form method="POST" action="{{ $formAction }}" id="scoreSheetForm">
                @csrf
                @if ($editingSheet)
                    @method('PUT')
                @endif

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">種別</label>
                        <select name="sheet_type" class="form-select">
                            @php $sheetType = old('sheet_type', $editingSheet->sheet_type ?? 'shootout'); @endphp
                            <option value="shootout" @selected($sheetType === 'shootout')>シュートアウト</option>
                            <option value="step_ladder" @selected($sheetType === 'step_ladder')>ステップラダー</option>
                            <option value="round_robin" @selected($sheetType === 'round_robin')>ラウンドロビン</option>
                            <option value="single_elimination" @selected($sheetType === 'single_elimination')>トーナメント</option>
                            <option value="custom" @selected($sheetType === 'custom')>その他</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ステージコード</label>
                        <input type="text" name="stage_code" class="form-control" value="{{ old('stage_code', $editingSheet->stage_code ?? 'shootout') }}" placeholder="例：shootout">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">マッチコード</label>
                        <input type="text" name="match_code" class="form-control" value="{{ old('match_code', $editingSheet->match_code ?? '') }}" placeholder="例：SO_FINAL">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">表示順</label>
                        <input type="number" name="match_order" class="form-control" value="{{ old('match_order', $editingSheet->match_order ?? 0) }}" min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">マッチ名</label>
                        <input type="text" name="match_label" class="form-control" value="{{ old('match_label', $editingSheet->match_label ?? '') }}" placeholder="例：優勝決定戦">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">ゲームNo</label>
                        <input type="number" name="game_number" class="form-control" value="{{ old('game_number', $editingSheet->game_number ?? 1) }}" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">レーン表示</label>
                        <input type="text" name="lane_label" class="form-control" value="{{ old('lane_label', $editingSheet->lane_label ?? '') }}" placeholder="例：21L / 22L">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-3">
                        <div class="form-check">
                            <input type="hidden" name="is_published" value="0">
                            <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published" @checked(old('is_published', $editingSheet->is_published ?? true))>
                            <label class="form-check-label" for="is_published">PDF表示対象</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="confirmed" value="1" class="form-check-input" id="confirmed" @checked(old('confirmed', $editingSheet?->confirmed_at ? true : false))>
                            <label class="form-check-label" for="confirmed">確定</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">メモ</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $editingSheet->notes ?? '') }}</textarea>
                    </div>
                </div>

                <div class="alert alert-info small">
                    入力記号：ストライクは <strong>X</strong>、スペアは <strong>/</strong>、ミスは <strong>-</strong>、ファールは <strong>F</strong>、ピン数は <strong>0〜9</strong> で入力してください。保存時にサーバー側でも再計算します。
                </div>

                @foreach ($defaultPlayers as $playerIndex => $player)
                    <div class="score-player-block border rounded p-3 mb-4" data-player-index="{{ $playerIndex }}">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-1">
                                <label class="form-label">枠</label>
                                <input type="text" name="players[{{ $playerIndex }}][player_slot]" class="form-control" value="{{ old("players.$playerIndex.player_slot", $player['player_slot']) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">登録選手から選択</label>
                                <select name="players[{{ $playerIndex }}][pro_bowler_id]" class="form-select player-select" data-player-index="{{ $playerIndex }}">
                                    <option value="">直接入力 / 未選択</option>
                                    @foreach ($playerOptions as $option)
                                        <option value="{{ $option->id }}"
                                            data-license="{{ $option->license_no }}"
                                            data-name="{{ $option->name_kanji }}"
                                            data-kana="{{ $option->name_kana }}"
                                            data-arm="{{ $option->dominant_arm }}"
                                            @selected((string)old("players.$playerIndex.pro_bowler_id", $player['pro_bowler_id']) === (string)$option->id)>
                                            {{ $option->license_no }} / {{ $option->name_kanji }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ライセンスNo</label>
                                <input type="text" name="players[{{ $playerIndex }}][pro_bowler_license_no]" class="form-control player-license" value="{{ old("players.$playerIndex.pro_bowler_license_no", $player['pro_bowler_license_no']) }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">選手名</label>
                                <input type="text" name="players[{{ $playerIndex }}][display_name]" class="form-control player-name" value="{{ old("players.$playerIndex.display_name", $player['display_name']) }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">フリガナ</label>
                                <input type="text" name="players[{{ $playerIndex }}][name_kana]" class="form-control player-kana" value="{{ old("players.$playerIndex.name_kana", $player['name_kana']) }}">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">投球</label>
                                <input type="text" name="players[{{ $playerIndex }}][dominant_arm]" class="form-control player-arm" value="{{ old("players.$playerIndex.dominant_arm", $player['dominant_arm']) }}" placeholder="右/左">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">レーン</label>
                                <input type="text" name="players[{{ $playerIndex }}][lane_label]" class="form-control" value="{{ old("players.$playerIndex.lane_label", $player['lane_label']) }}">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle text-center score-table">
                                <thead class="table-light">
                                    <tr>
                                        @for ($frameNo = 1; $frameNo <= 10; $frameNo++)
                                            <th style="min-width: {{ $frameNo === 10 ? '120px' : '90px' }};">{{ $frameNo }}</th>
                                        @endfor
                                        <th style="min-width: 80px;">計算</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @for ($frameNo = 1; $frameNo <= 10; $frameNo++)
                                            @php
                                                $frame = old("players.$playerIndex.frames.$frameNo", $player['frames'][$frameNo] ?? []);
                                            @endphp
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <input type="text" maxlength="2" class="form-control form-control-sm text-center frame-input" name="players[{{ $playerIndex }}][frames][{{ $frameNo }}][throw1]" value="{{ $frame['throw1'] ?? '' }}" data-player-index="{{ $playerIndex }}" data-frame="{{ $frameNo }}">
                                                    <input type="text" maxlength="2" class="form-control form-control-sm text-center frame-input" name="players[{{ $playerIndex }}][frames][{{ $frameNo }}][throw2]" value="{{ $frame['throw2'] ?? '' }}" data-player-index="{{ $playerIndex }}" data-frame="{{ $frameNo }}">
                                                    @if ($frameNo === 10)
                                                        <input type="text" maxlength="2" class="form-control form-control-sm text-center frame-input" name="players[{{ $playerIndex }}][frames][{{ $frameNo }}][throw3]" value="{{ $frame['throw3'] ?? '' }}" data-player-index="{{ $playerIndex }}" data-frame="{{ $frameNo }}">
                                                    @endif
                                                </div>
                                                <div class="small text-muted mt-1 cumulative-preview" data-player-index="{{ $playerIndex }}" data-frame="{{ $frameNo }}">{{ $frame['cumulative_score'] ?? '' }}</div>
                                            </td>
                                        @endfor
                                        <td>
                                            <div class="fw-bold fs-5 total-preview" data-player-index="{{ $playerIndex }}">{{ $player['final_score'] ?? 0 }}</div>
                                            @if ($player['is_winner'])
                                                <span class="badge bg-danger">勝者</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">保存して自動計算</button>
                    @if ($editingSheet)
                        <a href="{{ route('tournaments.match_score_sheets.index', $tournament) }}" class="btn btn-outline-secondary">新規登録に戻る</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-secondary text-white">登録済みスコアシート</div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>表示順</th>
                        <th>種別</th>
                        <th>マッチ</th>
                        <th>選手</th>
                        <th>勝者</th>
                        <th>PDF</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sheets as $sheet)
                        <tr>
                            <td>{{ $sheet->match_order }}</td>
                            <td>{{ $sheet->sheet_type }}</td>
                            <td>{{ $sheet->match_label ?: $sheet->match_code }}</td>
                            <td>
                                @foreach ($sheet->players as $player)
                                    <div>{{ $player->display_name }}：{{ $player->final_score }}</div>
                                @endforeach
                            </td>
                            <td>{{ optional($sheet->players->firstWhere('is_winner', true))->display_name ?? '-' }}</td>
                            <td>{{ $sheet->is_published ? '表示' : '非表示' }}</td>
                            <td>
                                <a href="{{ route('tournaments.match_score_sheets.edit', [$tournament, $sheet]) }}" class="btn btn-sm btn-outline-primary">編集</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">まだスコアシートはありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    function normalizeMark(value) {
        let mark = String(value || '').trim().toUpperCase();
        mark = mark.replace('×', 'X').replace('Ｘ', 'X').replace('ｘ', 'X');
        mark = mark.replace('ー', '-').replace('－', '-').replace('―', '-');
        if (mark === '.' || mark === '') return '';
        if (['X', '/', '-', 'F'].includes(mark)) return mark;
        if (/^[0-9]$/.test(mark)) return mark;
        return '';
    }

    function pinsFirst(mark) {
        if (mark === 'X') return 10;
        if (mark === '-' || mark === 'F' || mark === '') return 0;
        return /^[0-9]$/.test(mark) ? Math.max(0, Math.min(9, parseInt(mark, 10))) : 0;
    }

    function pinsSecond(mark) {
        if (mark === 'X') return 10;
        if (mark === '-' || mark === 'F' || mark === '') return 0;
        if (mark === '/') return 10;
        return /^[0-9]$/.test(mark) ? Math.max(0, Math.min(9, parseInt(mark, 10))) : 0;
    }

    function calculatePlayer(playerIndex) {
        const rolls = [];
        const frameStart = {};
        const frameValues = {};

        for (let frameNo = 1; frameNo <= 10; frameNo++) {
            const selectorBase = `[name^="players[${playerIndex}][frames][${frameNo}]"]`;
            const inputs = document.querySelectorAll(selectorBase);
            const t1 = normalizeMark(inputs[0] ? inputs[0].value : '');
            const t2 = normalizeMark(inputs[1] ? inputs[1].value : '');
            const t3 = normalizeMark(inputs[2] ? inputs[2].value : '');
            frameStart[frameNo] = rolls.length;
            frameValues[frameNo] = {t1, t2, t3};

            if (frameNo < 10) {
                if (t1 === '') continue;
                if (t1 === 'X') {
                    rolls.push(10);
                } else {
                    const p1 = pinsFirst(t1);
                    const p2 = t2 === '/' ? Math.max(0, 10 - p1) : pinsSecond(t2);
                    rolls.push(p1, p2);
                }
            } else {
                if (t1 !== '') {
                    const p1 = pinsFirst(t1);
                    rolls.push(p1);
                    if (t2 !== '') {
                        const p2 = t2 === '/' ? Math.max(0, 10 - p1) : pinsSecond(t2);
                        rolls.push(p2);
                        if (t3 !== '') {
                            const p3 = t3 === '/' ? Math.max(0, 10 - p2) : pinsSecond(t3);
                            rolls.push(p3);
                        }
                    }
                }
            }
        }

        let total = 0;
        for (let frameNo = 1; frameNo <= 10; frameNo++) {
            const start = frameStart[frameNo] || 0;
            const v = frameValues[frameNo] || {t1: '', t2: '', t3: ''};
            let score = null;

            if (frameNo < 10) {
                if (v.t1 === '') {
                    score = null;
                } else if (v.t1 === 'X') {
                    if (rolls[start] !== undefined && rolls[start + 1] !== undefined && rolls[start + 2] !== undefined) {
                        score = 10 + rolls[start + 1] + rolls[start + 2];
                    }
                } else if (v.t2 === '/') {
                    if (rolls[start] !== undefined && rolls[start + 1] !== undefined && rolls[start + 2] !== undefined) {
                        score = 10 + rolls[start + 2];
                    }
                } else if (v.t2 !== '') {
                    if (rolls[start] !== undefined && rolls[start + 1] !== undefined) {
                        score = rolls[start] + rolls[start + 1];
                    }
                }
            } else {
                score = rolls.slice(start).reduce((sum, val) => sum + val, 0);
            }

            const preview = document.querySelector(`.cumulative-preview[data-player-index="${playerIndex}"][data-frame="${frameNo}"]`);
            if (score !== null) {
                total += score;
                if (preview) preview.textContent = total;
            } else if (preview) {
                preview.textContent = '';
            }
        }

        const totalPreview = document.querySelector(`.total-preview[data-player-index="${playerIndex}"]`);
        if (totalPreview) totalPreview.textContent = total;
    }

    document.querySelectorAll('.frame-input').forEach(input => {
        input.addEventListener('input', function () {
            this.value = normalizeMark(this.value);
            calculatePlayer(this.dataset.playerIndex);
        });
    });

    document.querySelectorAll('.player-select').forEach(select => {
        select.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const block = this.closest('.score-player-block');
            if (!selected || !block || !selected.value) return;
            block.querySelector('.player-license').value = selected.dataset.license || '';
            block.querySelector('.player-name').value = selected.dataset.name || '';
            block.querySelector('.player-kana').value = selected.dataset.kana || '';
            block.querySelector('.player-arm').value = selected.dataset.arm || '';
        });
    });

    document.querySelectorAll('.score-player-block').forEach(block => calculatePlayer(block.dataset.playerIndex));
})();
</script>
@endsection
