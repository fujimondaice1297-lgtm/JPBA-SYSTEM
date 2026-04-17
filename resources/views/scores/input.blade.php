@extends('layouts.app')

@section('content')
@php
    /** @var array<int,array{id?:string,score?:string|int}> $oldRows */
    $oldRows = old('rows', []);
    $rowCount = max(count($oldRows), 10);

    $oldTournament = old('tournament_id');
    $oldStage      = old('stage');
    $oldGame       = old('game_number');
    $oldShift      = old('shift');
    $oldIdType     = old('identifier_type');
    $oldGender     = old('gender');

    $hasErr = function(string $key) use ($errors): bool {
        return $errors->has($key);
    };

    $stageSettingsMapSafe = $stageSettingsMap ?? [];
    $hasServerErrorsFlag  = (bool) $errors->any();
    $resultBaseUrl        = url('/scores/result');

    $playerLookupRowsSafe = collect(
        $playerLookupRows
        ?? \App\Models\ProBowler::query()
            ->select(['license_no', 'name_kanji'])
            ->whereNotNull('license_no')
            ->whereNotNull('name_kanji')
            ->orderBy('license_no')
            ->get()
    )->map(function ($row) {
        return [
            'license_no' => (string)($row->license_no ?? ''),
            'name_kanji' => (string)($row->name_kanji ?? ''),
        ];
    })->values();

    $entryPlayerMapSafe = collect($entryPlayerMap ?? [])->map(function ($rows) {
        return collect($rows)->map(function ($row) {
            return [
                'license_no'     => (string)($row['license_no'] ?? ''),
                'license_digits' => (string)($row['license_digits'] ?? ''),
                'license_last4'  => (string)($row['license_last4'] ?? ''),
                'name_kanji'     => (string)($row['name_kanji'] ?? ''),
                'gender'         => (string)($row['gender'] ?? ''),
            ];
        })->values();
    });
@endphp

<div class="p-4">
    <div class="mb-4">
        <h2 class="text-2xl font-bold mb-1">速報入力ページ</h2>
        <div class="text-sm text-gray-600">
            このページでスコア入力・ステージ設定を行い、速報ページへ遷移します。
        </div>
    </div>

    @if($errors->any())
        <div class="mb-3 text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
            @foreach($errors->all() as $e)
                <div>※ {{ $e }}</div>
            @endforeach
        </div>
    @endif

    @if(session('success'))
        <div class="text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2 mb-3">
            {{ session('success') }}
        </div>
    @endif

    @if(session('ok'))
        <div class="text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2 mb-3">
            {{ session('ok') }}
        </div>
    @endif

    <form method="POST" action="/scores/settings/bulk" class="mb-5" id="stageSettingForm">
        @csrf
        <div class="flex flex-wrap items-center gap-3 mb-2">
            <label>大会：
                <select name="tournament_id" id="settingTournament" required class="border px-2 py-1">
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}" @selected((string)$oldTournament === (string)$t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="bg-gray-200 text-black border px-3 py-1 rounded hover:bg-gray-300">ステージ設定を保存</button>
        </div>

        @php $defaults = ['予選'=>6,'準々決勝'=>4,'準決勝'=>3,'決勝'=>2]; @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($defaults as $label => $defG)
                <label class="border rounded p-2 flex items-center justify-between">
                    <span class="mr-2">
                        <input type="checkbox"
                               name="stages[{{ $label }}][enabled]"
                               value="1"
                               data-stage-enabled="{{ $label }}">
                        {{ $label }}
                    </span>
                    <span>
                        <input type="number"
                               name="stages[{{ $label }}][total_games]"
                               class="border px-1 py-0.5 w-16"
                               placeholder="{{ $defG }}"
                               min="0"
                               data-stage-games="{{ $label }}">
                        G
                    </span>
                </label>
            @endforeach
        </div>
    </form>

    <form method="POST" action="/scores/store" id="scoreForm" class="mb-4">
        @csrf

        <div class="flex flex-wrap items-end gap-4 mb-2">
            <label>大会：
                <select name="tournament_id" id="tournamentSelect" required class="border px-2 py-1">
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}" @selected((string)$oldTournament === (string)$t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>ステージ：
                <select name="stage" id="stageInput" required class="border px-2 py-1 min-w-[8rem]" data-old="{{ $oldStage ?? '' }}"></select>
            </label>

            <label>ゲーム番号：
                <select name="game_number" id="gameSelect" required class="border px-2 py-1" data-old="{{ $oldGame ?? '' }}"></select>
            </label>

            <label>シフト：
                <select name="shift" id="shiftInput" class="border px-2 py-1" data-old="{{ $oldShift ?? '' }}">
                    <option value="">（指定なし）</option>
                    <option value="A" @selected($oldShift === 'A')>A</option>
                    <option value="B" @selected($oldShift === 'B')>B</option>
                    <option value="C" @selected($oldShift === 'C')>C</option>
                </select>
            </label>

            <label>識別方法：
                <select name="identifier_type" id="identifierType" required class="border px-2 py-1">
                    <option value="license_number" @selected($oldIdType === 'license_number' || !$oldIdType)>ライセンス番号</option>
                    <option value="entry_number" @selected($oldIdType === 'entry_number')>エントリーナンバー</option>
                    <option value="name" @selected($oldIdType === 'name')>氏名</option>
                </select>
            </label>

            <label>性別：
                <select name="gender" id="genderSelect" class="border px-2 py-1">
                    <option value="" @selected($oldGender === '')>指定なし</option>
                    <option value="M" @selected($oldGender === 'M')>男子(M)</option>
                    <option value="F" @selected($oldGender === 'F')>女子(F)</option>
                </select>
            </label>

            <label id="carryWrap" style="display:none;">
                <input type="checkbox" id="carryPrelimChk"> 予選スコアを持ち込む
            </label>

            <label id="carrySemiWrap" style="display:none;">
                <input type="checkbox" id="carrySemiChk"> 準決勝スコアを持ち込む
            </label>

            <span id="carryHint" class="text-xs text-gray-500" style="display:none;">※ 速報表示に反映（保存不要）</span>
        </div>

        <div id="settingSummary" class="mt-1 mb-3 text-sm text-gray-700"></div>

        <div class="mb-3">
            <button type="button" id="openResultBtn" class="bg-blue-600 text-white border border-blue-700 px-4 py-2 rounded hover:bg-blue-700">
                この条件で速報ページを開く
            </button>
        </div>

        <table id="inputTable" class="table-auto border border-gray-300 w-full">
            <thead>
            <tr>
                <th class="border px-2 py-1 text-left">識別番号 / 氏名</th>
                <th class="border px-2 py-1 text-left">照合情報</th>
                <th class="border px-2 py-1 text-left">過去点数</th>
                <th class="border px-2 py-1 text-left">今回スコア</th>
                <th class="border px-2 py-1 text-left">今回込合計</th>
            </tr>
            </thead>
            <tbody>
            @for ($i = 0; $i < $rowCount; $i++)
                @php
                    $vId    = $oldRows[$i]['id'] ?? '';
                    $vScore = $oldRows[$i]['score'] ?? '';
                    $errId  = $hasErr("rows.$i.id");
                    $errSc  = $hasErr("rows.$i.score");
                @endphp
                <tr>
                    <td class="border px-1">
                        <input type="text"
                               name="rows[{{ $i }}][id]"
                               class="w-40 p-1 identifier-input {{ $errId ? 'cell-error' : '' }}"
                               data-row="{{ $i }}" data-col="0"
                               autocomplete="off"
                               placeholder="4桁 or 氏名"
                               value="{{ old("rows.$i.id", $vId) }}">
                    </td>
                    <td class="border px-2 text-sm text-gray-700 lookup-cell" data-row-lookup="{{ $i }}">-</td>
                    <td class="border px-2 text-sm text-gray-700 history-cell" data-row-history="{{ $i }}">-</td>
                    <td class="border px-1">
                        <input type="text"
                               name="rows[{{ $i }}][score]"
                               class="w-20 p-1 score-input {{ $errSc ? 'cell-error' : '' }}"
                               data-row="{{ $i }}" data-col="1"
                               autocomplete="off"
                               inputmode="numeric"
                               placeholder="3桁"
                               value="{{ old("rows.$i.score", $vScore) }}">
                    </td>
                    <td class="border px-2 text-sm font-semibold total-cell" data-row-total="{{ $i }}">-</td>
                </tr>
            @endfor
            </tbody>
        </table>

        <datalist id="entryLicenseCandidates"></datalist>
        <datalist id="entryNameCandidates"></datalist>

        <div class="mt-4">
            <button type="submit" class="bg-blue-200 text-black border border-blue-500 px-4 py-2 rounded hover:bg-blue-300">
                登録
            </button>
        </div>
    </form>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <form method="POST" action="/scores/clear-game" onsubmit="return confirm('このゲームの入力を削除します。よろしいですか？');" class="inline">
            @csrf
            <input type="hidden" name="tournament_id" id="cg_tid">
            <input type="hidden" name="stage" id="cg_stage">
            <input type="hidden" name="game_number" id="cg_game">
            <input type="hidden" name="shift" id="cg_shift">
            <input type="hidden" name="gender" id="cg_gender">
            <button class="bg-amber-100 text-amber-900 border border-amber-400 px-3 py-1 rounded">このゲームをクリア</button>
        </form>

        <form method="POST" action="/scores/clear-all" onsubmit="return confirm('このステージの入力をすべて削除します。よろしいですか？');" class="inline">
            @csrf
            <input type="hidden" name="tournament_id" id="ca_tid">
            <input type="hidden" name="stage" id="ca_stage">
            <input type="hidden" name="shift" id="ca_shift">
            <input type="hidden" name="gender" id="ca_gender">
            <button class="bg-red-100 text-red-900 border border-red-400 px-3 py-1 rounded">全クリア</button>
        </form>
    </div>

    <div class="mt-6 border rounded p-3 bg-gray-50">
        <div class="font-semibold mb-2">個別修正 / 削除（大会・ステージ・G・シフト・性別は上の選択を使用）</div>
        <div class="flex flex-wrap gap-3 items-end">
            <form method="POST" action="/scores/update-one" class="flex flex-wrap gap-2 items-end">
                @csrf
                <input type="hidden" name="tournament_id" id="uo_tid">
                <input type="hidden" name="stage" id="uo_stage">
                <input type="hidden" name="game_number" id="uo_game">
                <input type="hidden" name="shift" id="uo_shift">
                <input type="hidden" name="gender" id="uo_gender">
                <label>識別方法：
                    <select name="identifier_type" class="border px-2 py-1">
                        <option value="license_number">ライセンス</option>
                        <option value="entry_number">エントリー</option>
                        <option value="name">氏名</option>
                    </select>
                </label>
                <label>識別値：
                    <input type="text" name="identifier" class="border px-2 py-1 w-40" placeholder="4桁/番号/氏名">
                </label>
                <label>スコア：
                    <input type="number" name="score" class="border px-2 py-1 w-24" min="0" max="300">
                </label>
                <button class="bg-emerald-100 border border-emerald-400 text-emerald-900 px-3 py-1 rounded">更新</button>
            </form>

            <form method="POST" action="/scores/delete-one" class="flex flex-wrap gap-2 items-end" onsubmit="return confirm('該当データを削除します。よろしいですか？')">
                @csrf
                <input type="hidden" name="tournament_id" id="do_tid">
                <input type="hidden" name="stage" id="do_stage">
                <input type="hidden" name="game_number" id="do_game">
                <input type="hidden" name="shift" id="do_shift">
                <input type="hidden" name="gender" id="do_gender">
                <label>識別方法：
                    <select name="identifier_type" class="border px-2 py-1">
                        <option value="license_number">ライセンス</option>
                        <option value="entry_number">エントリー</option>
                        <option value="name">氏名</option>
                    </select>
                </label>
                <label>識別値：
                    <input type="text" name="identifier" class="border px-2 py-1 w-40" placeholder="4桁/番号/氏名">
                </label>
                <button class="bg-rose-100 border border-rose-400 text-rose-900 px-3 py-1 rounded">削除</button>
            </form>
        </div>
    </div>
</div>

<style>
    .cell-error {
        background: #ffecec;
        border-color: #ff7b7b !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const hasServerErrors = @json($hasServerErrorsFlag);
    const resultBaseUrl = @json($resultBaseUrl);
    const playerLookupRows = @json($playerLookupRowsSafe);
    const entryPlayerMap = @json($entryPlayerMapSafe);

    function normalizeMap(raw) {
        const out = {};
        for (const tid in (raw || {})) {
            const inner = {};
            const src = raw[tid] || {};
            for (const k in src) {
                const g = parseInt(src[k] ?? 0, 10);
                if (g > 0) inner[k] = g;
            }
            if (Object.keys(inner).length) out[tid] = inner;
        }
        return out;
    }

    function normalizeDigits(value) {
        const digits = String(value || '').replace(/\D+/g, '').replace(/^0+/, '');
        return digits || '';
    }

    function normalizeName(value) {
        return String(value || '').trim().replace(/\s+/g, ' ');
    }

    function normalizeIdentifierValue(value, identifierType) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        if (identifierType === 'license_number') return normalizeDigits(raw);
        if (identifierType === 'entry_number') return raw;
        return normalizeName(raw);
    }

    function buildPlayerLookupMaps(rows) {
        const byDigits = {};
        const byName = {};

        (rows || []).forEach((row) => {
            const licenseNo = String(row.license_no || '');
            const nameKanji = String(row.name_kanji || '');
            const digitsKey = normalizeDigits(licenseNo);
            const nameKey = normalizeName(nameKanji);
            const item = {
                license_no: licenseNo,
                name_kanji: nameKanji,
                gender: String(licenseNo || '').charAt(0).toUpperCase(),
            };

            if (digitsKey) {
                byDigits[digitsKey] = byDigits[digitsKey] || [];
                byDigits[digitsKey].push(item);
            }

            if (nameKey) {
                byName[nameKey] = byName[nameKey] || [];
                byName[nameKey].push(item);
            }
        });

        return { byDigits, byName };
    }

    const playerLookupMaps = buildPlayerLookupMaps(playerLookupRows);

    let stageMapByTournament = normalizeMap(@json($stageSettingsMapSafe));
    let liveHistoryMap = {};
    let livePrevKeys = [];
    let liveExistsThisGame = [];
    let liveAmbiguousKeys = [];

    const tSel = document.getElementById('tournamentSelect');
    const sSel = document.getElementById('stageInput');
    const gSel = document.getElementById('gameSelect');
    const idType = document.getElementById('identifierType');
    const shiftSel = document.getElementById('shiftInput');
    const genderSel = document.getElementById('genderSelect');
    const summary = document.getElementById('settingSummary');
    const carryWrap = document.getElementById('carryWrap');
    const carryHint = document.getElementById('carryHint');
    const carryChk = document.getElementById('carryPrelimChk');
    const carrySemiWrap = document.getElementById('carrySemiWrap');
    const carrySemiChk = document.getElementById('carrySemiChk');
    const openBtn = document.getElementById('openResultBtn');
    const tableBody = document.querySelector('#inputTable tbody');
    const settingTournament = document.getElementById('settingTournament');
    const licenseDatalist = document.getElementById('entryLicenseCandidates');
    const nameDatalist = document.getElementById('entryNameCandidates');

    const STORE_KEY = 'jpba:scores:input:last';
    const STAGE_ORDER = ['予選', '準々決勝', '準決勝', '決勝'];
    const DEFAULT_STAGE_MAP = { '予選': 6, '準々決勝': 4, '準決勝': 3, '決勝': 2 };

    function ensureStageMap(tid) {
        if (!stageMapByTournament[tid] || !Object.keys(stageMapByTournament[tid]).length) {
            stageMapByTournament[tid] = { ...DEFAULT_STAGE_MAP };
        }
        return stageMapByTournament[tid];
    }

    function saveSelection() {
        const data = {
            tournament_id: tSel.value,
            stage: sSel.value,
            game_number: gSel.value,
            shift: shiftSel.value,
            identifier_type: idType.value,
            gender: genderSel.value,
            carry: (carryWrap.style.display === 'none') ? 0 : (carryChk.checked ? 1 : 0),
        };
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify(data));
        } catch (e) {}
    }

    function loadSelection() {
        try {
            const raw = localStorage.getItem(STORE_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function sortedKeysByOrder(map) {
        return Object.keys(map || {}).sort((a, b) => {
            const ai = STAGE_ORDER.indexOf(a);
            const bi = STAGE_ORDER.indexOf(b);
            return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        });
    }

    function carryKey() {
        return `carryPrelim:t${tSel.value}:stage:${sSel.value}`;
    }

    function carryKeySemi() {
        return `carrySemi:t${tSel.value}:stage:${sSel.value}`;
    }

    function getStageEnabledInput(label) {
        return document.querySelector(`[data-stage-enabled="${label}"]`);
    }

    function getStageGamesInput(label) {
        return document.querySelector(`[data-stage-games="${label}"]`);
    }

    function fillStageSettingEditor(tid) {
        const map = ensureStageMap(String(tid));
        STAGE_ORDER.forEach((label) => {
            const enabledEl = getStageEnabledInput(label);
            const gamesEl = getStageGamesInput(label);
            const g = parseInt(map[label] ?? 0, 10);

            if (enabledEl) enabledEl.checked = g > 0;
            if (gamesEl) gamesEl.value = g > 0 ? String(g) : '';
        });
    }

    function refillSummary() {
        const map = ensureStageMap(tSel.value);
        const parts = sortedKeysByOrder(map).map(k => `${k}${map[k]}G`);
        summary.textContent = parts.length ? `設定：${parts.join(' / ')}` : '設定：未登録';
    }

    function refillStages() {
        const map = ensureStageMap(tSel.value);
        sSel.innerHTML = '';

        sortedKeysByOrder(map).forEach(stage => {
            const opt = document.createElement('option');
            opt.value = stage;
            opt.textContent = stage;
            sSel.appendChild(opt);
        });

        const oldStage = sSel.dataset.old || '';
        const saved = loadSelection();

        if (saved && saved.stage && [...sSel.options].some(o => o.value === saved.stage) && !hasServerErrors) {
            sSel.value = saved.stage;
        } else if (oldStage && [...sSel.options].some(o => o.value === oldStage)) {
            sSel.value = oldStage;
        } else if (sSel.options.length > 0) {
            sSel.selectedIndex = 0;
        }

        refillGames();
        refillSummary();
        showCarryOption();
        syncHidden();
    }

    function refillGames() {
        const map = ensureStageMap(tSel.value);
        const totalGames = parseInt(map[sSel.value] ?? 1, 10);

        gSel.innerHTML = '';
        for (let i = 1; i <= totalGames; i++) {
            const o = document.createElement('option');
            o.value = String(i);
            o.textContent = String(i);
            gSel.appendChild(o);
        }

        const oldGame = gSel.dataset.old || '';
        const saved = loadSelection();

        if (saved && saved.game_number && [...gSel.options].some(o => o.value === String(saved.game_number)) && !hasServerErrors) {
            gSel.value = String(saved.game_number);
        } else if (oldGame && [...gSel.options].some(o => o.value === String(oldGame))) {
            gSel.value = String(oldGame);
        } else if (gSel.options.length > 0) {
            gSel.selectedIndex = 0;
        }

        showCarryOption();
        syncHidden();
    }

    function showCarryOption() {
        const stageNow = sSel.value;
        const on = (stageNow && stageNow !== '予選');
        carryWrap.style.display = on ? '' : 'none';
        carryHint.style.display = on ? '' : 'none';

        if (on) {
            carryChk.checked = (localStorage.getItem(carryKey()) === '1');
        } else {
            carryChk.checked = false;
        }

        const showSemi = (stageNow === '決勝');
        carrySemiWrap.style.display = showSemi ? '' : 'none';
        if (showSemi) {
            carrySemiChk.checked = (localStorage.getItem(carryKeySemi()) === '1');
        } else {
            carrySemiChk.checked = false;
        }
    }

    function syncHidden() {
        const vals = {
            tid: tSel.value,
            stage: sSel.value,
            game: gSel.value,
            shift: shiftSel.value,
            gender: genderSel.value
        };

        ['cg', 'ca', 'uo', 'do'].forEach(p => {
            const set = (id, v) => {
                const el = document.getElementById(`${p}_${id}`);
                if (el) el.value = v;
            };
            set('tid', vals.tid);
            set('stage', vals.stage);
            set('game', vals.game);
            set('shift', vals.shift);
            set('gender', vals.gender);
        });
    }

    function getCurrentTournamentParticipants() {
        const tid = String(tSel.value || '');
        let items = entryPlayerMap[tid] || [];

        if (genderSel.value) {
            items = items.filter(item => String(item.gender || '').toUpperCase() === genderSel.value);
        }

        return items;
    }

    function rebuildParticipantDatalists() {
        licenseDatalist.innerHTML = '';
        nameDatalist.innerHTML = '';

        const gameNo = parseInt(gSel.value || '1', 10);
        if (gameNo < 2) {
            applyIdentifierDatalist();
            return;
        }

        const items = getCurrentTournamentParticipants();

        items.forEach((item) => {
            const last4 = String(item.license_last4 || '');
            const fullLicense = String(item.license_no || '');
            const name = String(item.name_kanji || '');

            if (last4 !== '' && name !== '') {
                const opt1 = document.createElement('option');
                opt1.value = last4;
                opt1.label = `${name} / ${fullLicense}`;
                licenseDatalist.appendChild(opt1);
            }

            if (name !== '') {
                const opt2 = document.createElement('option');
                opt2.value = name;
                opt2.label = fullLicense;
                nameDatalist.appendChild(opt2);
            }
        });

        applyIdentifierDatalist();
    }

    function applyIdentifierDatalist() {
        const gameNo = parseInt(gSel.value || '1', 10);
        const participants = getCurrentTournamentParticipants();
        const enabled = gameNo >= 2 && participants.length > 0;

        document.querySelectorAll('.identifier-input').forEach((el) => {
            el.removeAttribute('list');

            if (!enabled) {
                return;
            }

            if (idType.value === 'license_number') {
                el.setAttribute('list', 'entryLicenseCandidates');
            } else if (idType.value === 'name') {
                el.setAttribute('list', 'entryNameCandidates');
            }
        });
    }

    function addRow(index) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="border px-1">
                <input type="text" name="rows[${index}][id]" class="w-40 p-1 identifier-input"
                       data-row="${index}" data-col="0" autocomplete="off" placeholder="4桁 or 氏名">
            </td>
            <td class="border px-2 text-sm text-gray-700 lookup-cell" data-row-lookup="${index}">-</td>
            <td class="border px-2 text-sm text-gray-700 history-cell" data-row-history="${index}">-</td>
            <td class="border px-1">
                <input type="text" name="rows[${index}][score]" class="w-20 p-1 score-input"
                       data-row="${index}" data-col="1" autocomplete="off" inputmode="numeric" placeholder="3桁">
            </td>
            <td class="border px-2 text-sm font-semibold total-cell" data-row-total="${index}">-</td>`;
        tableBody.appendChild(tr);
        applyIdentifierDatalist();
    }

    function filterCandidatesByGender(candidates) {
        const g = genderSel.value || '';
        if (!g) return candidates;
        return (candidates || []).filter(item => String(item.gender || '').toUpperCase() === g);
    }

    function renderLookupForRow(rowIndex) {
        const idEl = document.querySelector(`[data-row="${rowIndex}"][data-col="0"]`);
        const lookupCell = document.querySelector(`[data-row-lookup="${rowIndex}"]`);
        if (!idEl || !lookupCell) return;

        if (idType.value === 'entry_number') {
            lookupCell.textContent = '-';
            return;
        }

        const rawValue = idEl.value || '';
        if (!rawValue.trim()) {
            lookupCell.textContent = '-';
            return;
        }

        let candidates = [];
        if (idType.value === 'license_number') {
            candidates = playerLookupMaps.byDigits[normalizeDigits(rawValue)] || [];
        } else {
            candidates = playerLookupMaps.byName[normalizeName(rawValue)] || [];
        }

        candidates = filterCandidatesByGender(candidates);

        if (!candidates.length) {
            lookupCell.textContent = '-';
            return;
        }

        if (candidates.length > 1) {
            lookupCell.textContent = '候補複数（性別指定で確定）';
            return;
        }

        const match = candidates[0];
        if (idType.value === 'license_number') {
            lookupCell.textContent = `${match.name_kanji} / ${match.license_no}`;
        } else {
            lookupCell.textContent = `${match.license_no} / ${match.name_kanji}`;
        }
    }

    function renderHistoryForRow(rowIndex) {
        const idEl = document.querySelector(`[data-row="${rowIndex}"][data-col="0"]`);
        const scoreEl = document.querySelector(`[data-row="${rowIndex}"][data-col="1"]`);
        const historyCell = document.querySelector(`[data-row-history="${rowIndex}"]`);
        const totalCell = document.querySelector(`[data-row-total="${rowIndex}"]`);

        if (!idEl || !scoreEl || !historyCell || !totalCell) return;

        const key = normalizeIdentifierValue(idEl.value, idType.value);
        if (!key) {
            historyCell.textContent = '-';
            totalCell.textContent = '-';
            return;
        }

        if (idType.value === 'license_number' && liveAmbiguousKeys.includes(key)) {
            historyCell.textContent = '候補複数（性別指定）';
            totalCell.textContent = '-';
            return;
        }

        const entry = liveHistoryMap[key] || null;
        if (!entry) {
            historyCell.textContent = (parseInt(gSel.value || '1', 10) > 1) ? '前ゲーム無し' : '-';
            const currentScore = parseInt(scoreEl.value || '0', 10);
            totalCell.textContent = currentScore > 0 ? String(currentScore) : '-';
            return;
        }

        const scoreParts = Object.keys(entry.scores || {})
            .sort((a, b) => Number(a) - Number(b))
            .map(gameNo => `${gameNo}G:${entry.scores[gameNo]}`);

        historyCell.textContent = scoreParts.length ? scoreParts.join(' / ') : '-';

        const previousTotal = parseInt(entry.total || '0', 10);
        const currentScore = parseInt(scoreEl.value || '0', 10);
        totalCell.textContent = currentScore > 0 ? String(previousTotal + currentScore) : String(previousTotal);
    }

    function renderAllRows() {
        document.querySelectorAll('.identifier-input').forEach((idEl) => {
            const rowIndex = idEl.dataset.row;
            renderLookupForRow(rowIndex);
            renderHistoryForRow(rowIndex);
        });
    }

    async function refreshExistingIds() {
        const params = new URLSearchParams({
            tournament_id: tSel.value,
            stage: sSel.value,
            game_number: gSel.value,
            shift: shiftSel.value || '',
            gender: genderSel.value || '',
            identifier_type: idType.value || 'license_number'
        });

        try {
            const res = await fetch(`/scores/api/existing-ids?${params.toString()}`);
            if (!res.ok) return;
            const data = await res.json();
            livePrevKeys = data.prevKeys || [];
            liveExistsThisGame = data.existsThisGame || [];
            liveHistoryMap = data.historyMap || {};
            liveAmbiguousKeys = data.ambiguousKeys || [];
            renderAllRows();
        } catch (e) {}
    }

    document.querySelector('#inputTable').addEventListener('input', function (e) {
        const t = e.target;
        if (!t.dataset) return;

        const row = parseInt(t.dataset.row || '0', 10);
        const col = parseInt(t.dataset.col || '0', 10);

        if (col === 0) {
            renderLookupForRow(row);
            renderHistoryForRow(row);

            const v = t.value.trim();
            if (idType.value === 'license_number' && /^\d{4}$/.test(v)) {
                const next = document.querySelector(`[data-row="${row}"][data-col="1"]`);
                if (next) next.focus();
            }
        }

        if (col === 1) {
            renderHistoryForRow(row);

            if (t.value.trim().length >= 3) {
                const nextRow = row + 1;
                let next = document.querySelector(`[data-row="${nextRow}"][data-col="0"]`);
                if (!next) {
                    addRow(nextRow);
                    next = document.querySelector(`[data-row="${nextRow}"][data-col="0"]`);
                }
                if (next) next.focus();
            }
        }
    });

    document.getElementById('scoreForm').addEventListener('submit', function (e) {
        saveSelection();

        if (carryWrap.style.display !== 'none') {
            localStorage.setItem(carryKey(), carryChk.checked ? '1' : '0');
        }
        if (carrySemiWrap.style.display !== 'none') {
            localStorage.setItem(carryKeySemi(), carrySemiChk.checked ? '1' : '0');
        }

        let hasErr = false;
        const seenThis = new Set();

        document.querySelectorAll('.identifier-input').forEach((idEl) => {
            const row = idEl.dataset.row;
            const scoreEl = document.querySelector(`[data-row="${row}"][data-col="1"]`);
            const idVal = (idEl.value || '').trim();
            const scVal = (scoreEl.value || '').trim();

            if (idVal === '' && scVal === '') return;

            const normalized = normalizeIdentifierValue(idVal, idType.value);
            const dupKey = (genderSel.value || '') + '#' + (shiftSel.value || '') + '#' + normalized;

            idEl.classList.remove('cell-error');
            scoreEl.classList.remove('cell-error');

            if (seenThis.has(dupKey)) {
                idEl.classList.add('cell-error');
                scoreEl.classList.add('cell-error');
                hasErr = true;
            } else {
                seenThis.add(dupKey);
            }

            if (parseInt(gSel.value, 10) > 1) {
                if (idType.value === 'license_number' && liveAmbiguousKeys.includes(normalized)) {
                    idEl.classList.add('cell-error');
                    hasErr = true;
                } else if (normalized && livePrevKeys.length && !livePrevKeys.includes(normalized)) {
                    idEl.classList.add('cell-error');
                    hasErr = true;
                }
            }

            if (normalized && liveExistsThisGame.includes(normalized)) {
                idEl.classList.add('cell-error');
                scoreEl.classList.add('cell-error');
                hasErr = true;
            }
        });

        if (hasErr) {
            e.preventDefault();
            alert('入力に問題があります。重複・未登録・候補複数を確認してください。');
        }
    });

    openBtn.addEventListener('click', function () {
        saveSelection();

        const shiftsCSV = shiftSel.value ? shiftSel.value : '';
        const carryPre  = carryWrap.style.display === 'none' ? 0 : (carryChk.checked ? 1 : 0);
        const carrySemi = carrySemiWrap.style.display === 'none' ? 0 : (carrySemiChk.checked ? 1 : 0);

        const params = new URLSearchParams({
            tournament_id: tSel.value,
            stage: sSel.value,
            upto_game: gSel.value,
            shifts: shiftsCSV,
            gender_filter: genderSel.value || '',
            border_type: 'rank',
            per_point: '200',
            carry_prelim: String(carryPre),
            carry_semifinal: String(carrySemi),
        });

        window.location.href = `${resultBaseUrl}?${params.toString()}`;
    });

    [tSel, sSel, gSel, shiftSel, idType, genderSel].forEach((el) => {
        el.addEventListener('change', function () {
            saveSelection();
            syncHidden();
            refillSummary();
            showCarryOption();
            rebuildParticipantDatalists();
            refreshExistingIds();
        });
    });

    if (carryChk) {
        carryChk.addEventListener('change', function () {
            localStorage.setItem(carryKey(), carryChk.checked ? '1' : '0');
        });
    }

    if (carrySemiChk) {
        carrySemiChk.addEventListener('change', function () {
            localStorage.setItem(carryKeySemi(), carrySemiChk.checked ? '1' : '0');
        });
    }

    settingTournament.addEventListener('change', function () {
        fillStageSettingEditor(settingTournament.value);
    });

    tSel.addEventListener('change', function () {
        settingTournament.value = tSel.value;
        fillStageSettingEditor(settingTournament.value);
        refillStages();
        rebuildParticipantDatalists();
        refreshExistingIds();
    });

    (function init() {
        const saved = loadSelection();

        if (saved && !hasServerErrors) {
            if ([...tSel.options].some(o => o.value === saved.tournament_id)) {
                tSel.value = saved.tournament_id;
            }
        }

        if ([...settingTournament.options].some(o => o.value === tSel.value)) {
            settingTournament.value = tSel.value;
        }

        fillStageSettingEditor(settingTournament.value);
        refillStages();

        if (!hasServerErrors) {
            const s2 = loadSelection() || {};
            if (s2.stage && [...sSel.options].some(o => o.value === s2.stage)) sSel.value = s2.stage;
            refillGames();
            if (typeof s2.shift === 'string' && [...shiftSel.options].some(o => o.value === s2.shift)) shiftSel.value = s2.shift;
            if (typeof s2.gender === 'string' && [...genderSel.options].some(o => o.value === s2.gender)) genderSel.value = s2.gender;
            if (s2.identifier_type && [...idType.options].some(o => o.value === s2.identifier_type)) idType.value = s2.identifier_type;
        }

        syncHidden();
        refillSummary();
        showCarryOption();
        rebuildParticipantDatalists();
        refreshExistingIds();
        saveSelection();
    })();
});
</script>
@endsection