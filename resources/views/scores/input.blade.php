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
@endphp

<div class="p-4">
    <h2 class="text-xl mb-4">スコア一括入力</h2>

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

    {{-- ステージ設定 --}}
    <form method="POST" action="/scores/settings/bulk" class="mb-4" id="stageSettingForm">
        @csrf
        <div class="flex items-center gap-3 mb-2">
            <label>大会：
                <select name="tournament_id" id="settingTournament" required class="border px-2 py-1">
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="bg-gray-200 text-black border px-3 py-1 rounded hover:bg-gray-300">ステージ設定を保存</button>
        </div>

        @php $defaults = ['予選'=>6,'準々決勝'=>4,'準決勝'=>3,'決勝'=>2]; @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($defaults as $label => $defG)
                <label class="border rounded p-2 flex items-center justify-between">
                    <span class="mr-2">
                        <input type="checkbox" name="stages[{{ $label }}][enabled]" value="1">
                        {{ $label }}
                    </span>
                    <span>
                        <input type="number" name="stages[{{ $label }}][total_games]" class="border px-1 py-0.5 w-16" placeholder="{{ $defG }}" min="0">
                        G
                    </span>
                </label>
            @endforeach
        </div>
    </form>

    {{-- スコア登録 --}}
    <form method="POST" action="/scores/store" id="scoreForm">
        @csrf

        <div class="flex flex-wrap items-end gap-4 mb-2">
            <label>大会：
                <select name="tournament_id" id="tournamentSelect" required class="border px-2 py-1">
                    @foreach($tournaments as $t)
                        <option value="{{ $t->id }}" @selected($oldTournament == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>ステージ：
                <select name="stage" id="stageInput" required class="border px-2 py-1 min-w-[8rem]"
                        data-old="{{ $oldStage ?? '' }}"></select>
            </label>

            <label>ゲーム番号：
                <select name="game_number" id="gameSelect" required class="border px-2 py-1" data-old="{{ $oldGame ?? '' }}"></select>
            </label>

            <label>シフト：
                <select name="shift" id="shiftInput" class="border px-2 py-1" data-old="{{ $oldShift ?? '' }}">
                    <option value="">（指定なし）</option>
                    <option value="A" @selected($oldShift==='A')>A</option>
                    <option value="B" @selected($oldShift==='B')>B</option>
                    <option value="C" @selected($oldShift==='C')>C</option>
                </select>
            </label>

            <label>識別方法：
                <select name="identifier_type" id="identifierType" required class="border px-2 py-1">
                    <option value="license_number" @selected($oldIdType==='license_number' || !$oldIdType)>ライセンス番号</option>
                    <option value="entry_number"   @selected($oldIdType==='entry_number')>エントリーナンバー</option>
                    <option value="name"           @selected($oldIdType==='name')>氏名</option>
                </select>
            </label>

            <label>性別：
                <select name="gender" id="genderSelect" class="border px-2 py-1">
                    <option value=""  @selected($oldGender==='')>指定なし</option>
                    <option value="M" @selected($oldGender==='M')>男子(M)</option>
                    <option value="F" @selected($oldGender==='F')>女子(F)</option>
                </select>
            </label>

            {{-- 予選持ち込み（既存） --}}
            <label id="carryWrap" style="display:none;">
                <input type="checkbox" id="carryPrelimChk"> 予選スコアを持ち込む
            </label>

            {{-- ★追加：決勝時だけ表示する準決勝持ち込み --}}
            <label id="carrySemiWrap" style="display:none;">
                <input type="checkbox" id="carrySemiChk"> 準決勝スコアを持ち込む
            </label>

            <span id="carryHint" class="text-xs text-gray-500" style="display:none;">※ 速報表示に反映（保存不要）</span>
        </div>

        <div id="settingSummary" class="mt-1 mb-2 text-sm text-gray-700"></div>

        <table id="inputTable" class="table-auto border border-gray-300">
            <thead>
            <tr>
                <th class="border px-2">識別番号 / 氏名</th>
                <th class="border px-2">スコア</th>
            </tr>
            </thead>
            <tbody>
            @for ($i = 0; $i < $rowCount; $i++)
                @php
                    $vId    = $oldRows[$i]['id']    ?? '';
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
                </tr>
            @endfor
            </tbody>
        </table>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="bg-blue-200 text-black border border-blue-500 px-4 py-2 rounded hover:bg-blue-300">
                登録
            </button>
            <button type="button" id="openResultBtn" class="bg-gray-200 text-black border px-4 py-2 rounded hover:bg-gray-300">
                速報ページを開く
            </button>

            {{-- このゲームだけ削除 --}}
            <form method="POST" action="/scores/clear-game" onsubmit="return confirm('このゲームの入力を削除します。よろしいですか？');" class="inline">
                @csrf
                <input type="hidden" name="tournament_id" id="cg_tid">
                <input type="hidden" name="stage" id="cg_stage">
                <input type="hidden" name="game_number" id="cg_game">
                <input type="hidden" name="shift" id="cg_shift">
                <input type="hidden" name="gender" id="cg_gender">
                <button class="bg-amber-100 text-amber-900 border border-amber-400 px-3 py-1 rounded">このゲームをクリア</button>
            </form>

            {{-- ステージ全削除 --}}
            <form method="POST" action="/scores/clear-all" onsubmit="return confirm('このステージの入力をすべて削除します。よろしいですか？');" class="inline">
                @csrf
                <input type="hidden" name="tournament_id" id="ca_tid">
                <input type="hidden" name="stage" id="ca_stage">
                <input type="hidden" name="shift" id="ca_shift">
                <input type="hidden" name="gender" id="ca_gender">
                <button class="bg-red-100 text-red-900 border border-red-400 px-3 py-1 rounded">全クリア</button>
            </form>
        </div>

        {{-- 個別修正/削除（既存のまま） --}}
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

                <form method="POST" action="/scores/delete-one" class="flex flex-wrap gap-2 items-end"
                      onsubmit="return confirm('該当データを削除します。よろしいですか？')">
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
    </form>
</div>

<style>
    .cell-error { background: #ffecec; border-color: #ff7b7b !important; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function normalizeMap(raw){
        const out={};
        for (const tid in (raw||{})){
            const inner={};
            const src = raw[tid] || {};
            for (const k in src){
                const g = parseInt(src[k] ?? 0, 10);
                if (g > 0) inner[k] = g;
            }
            if (Object.keys(inner).length) out[tid] = inner;
        }
        return out;
    }
    let stageMapByTournament = normalizeMap(@json($stageSettingsMap ?? []));

    const tSel = document.getElementById('tournamentSelect');
    const sSel = document.getElementById('stageInput');
    const gSel = document.getElementById('gameSelect');
    const idType = document.getElementById('identifierType');
    const shiftSel = document.getElementById('shiftInput');
    const genderSel= document.getElementById('genderSelect');
    const summary  = document.getElementById('settingSummary');
    const carryWrap= document.getElementById('carryWrap');
    const carryHint= document.getElementById('carryHint');
    const carryChk = document.getElementById('carryPrelimChk');

    // ★追加：準決勝持ち込みUI
    const carrySemiWrap = document.getElementById('carrySemiWrap');
    const carrySemiChk  = document.getElementById('carrySemiChk');

    const openBtn  = document.getElementById('openResultBtn');
    const tableBody= document.querySelector('#inputTable tbody');

    const STORE_KEY = 'jpba:scores:input:last';
    function saveSelection(){
        const data = {
            tournament_id: tSel.value,
            stage: sSel.value,
            game_number: gSel.value,
            shift: shiftSel.value,
            identifier_type: idType.value,
            gender: genderSel.value,
            carry: (carryWrap.style.display === 'none') ? 0 : (carryChk.checked ? 1 : 0),
            // ★準決勝持ち込みは LocalStorage には保存しない（結果プレビュー時だけ使う）
        };
        try { localStorage.setItem(STORE_KEY, JSON.stringify(data)); } catch(e){}
    }
    function loadSelection(){
        try{
            const raw = localStorage.getItem(STORE_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        }catch(e){ return null; }
    }

    const STAGE_ORDER = ['予選','準々決勝','準決勝','決勝'];
    function sortedKeysByOrder(map){
        return Object.keys(map||{}).sort((a,b)=>{
            const ai = STAGE_ORDER.indexOf(a), bi = STAGE_ORDER.indexOf(b);
            return (ai<0?99:ai) - (bi<0?99:bi);
        });
    }

    function carryKey(){ return `carryPrelim:t${tSel.value}:stage:${sSel.value}`; }
    // ★準決勝側も個別キー（決勝のときのみ使う）
    function carryKeySemi(){ return `carrySemi:t${tSel.value}:stage:${sSel.value}`; }

    function refillSummary(){
        const map = stageMapByTournament[tSel.value] || {};
        const parts = sortedKeysByOrder(map).map(k => `${k}${map[k]}G`);
        summary.textContent = parts.length ? `設定：${parts.join(' / ')}` : '設定：未登録（仮に 予選6G として扱います）';
    }

    function refillStages(){
        const tid = tSel.value;
        const map = stageMapByTournament[tid] || {};
        sSel.innerHTML = '';

        const keys = sortedKeysByOrder(map);
        if (keys.length){
            keys.forEach(stage=>{
                const opt=document.createElement('option'); opt.value=stage; opt.textContent=stage; sSel.appendChild(opt);
            });
        }else{
            stageMapByTournament[tid] = { '予選': 6 };
            const opt=document.createElement('option'); opt.value='予選'; opt.textContent='予選'; sSel.appendChild(opt);
        }

        const oldStage = sSel.dataset.old || '';
        if (oldStage && [...sSel.options].some(o=>o.value===oldStage)) sSel.value = oldStage;

        refillGames();
        refillSummary();
        showCarryOption();
        syncHidden();
        refreshExistingIds();
    }
    function refillGames(){
        const tid=tSel.value, map=stageMapByTournament[tid]||{}, stage=sSel.value, g=(map[stage]||6);
        gSel.innerHTML=''; for (let i=1;i<=g;i++){ const o=document.createElement('option'); o.value=i; o.textContent=i; gSel.appendChild(o); }

        const oldGame = gSel.dataset.old || '';
        if (oldGame && [...gSel.options].some(o=>o.value===String(oldGame))) gSel.value = String(oldGame);

        showCarryOption();
        syncHidden();
        refreshExistingIds();
    }
    function showCarryOption(){
        const stageNow = sSel.value;
        const on = (stageNow && stageNow !== '予選');
        carryWrap.style.display = on ? '' : 'none';
        carryHint.style.display = on ? '' : 'none';
        if (on) carryChk.checked = (localStorage.getItem(carryKey()) === '1');

        // ★決勝のときだけ準決勝持ち込みを表示
        const showSemi = (stageNow === '決勝');
        carrySemiWrap.style.display = showSemi ? '' : 'none';
        if (showSemi) {
            // 前回値があれば復元（あくまでプレビュー用の覚え）
            const saved = localStorage.getItem(carryKeySemi());
            carrySemiChk.checked = (saved === '1');
        } else {
            carrySemiChk.checked = false;
        }
    }

    function syncHidden(){
        const vals = {
            tid: tSel.value, stage: sSel.value, game: gSel.value,
            shift: shiftSel.value, gender: genderSel.value
        };
        ['cg','ca','uo','do'].forEach(p=>{
            const set = (id, v)=>{ const el=document.getElementById(`${p}_${id}`); if(el) el.value=v; };
            set('tid',   vals.tid);
            set('stage', vals.stage);
            set('game',  vals.game);
            set('shift', vals.shift);
            set('gender',vals.gender);
        });
    }

    function addRow(index){
        const tr=document.createElement('tr');
        tr.innerHTML = `
            <td class="border px-1">
                <input type="text" name="rows[${index}][id]" class="w-40 p-1 identifier-input"
                       data-row="${index}" data-col="0" autocomplete="off" placeholder="4桁 or 氏名">
            </td>
            <td class="border px-1">
                <input type="text" name="rows[${index}][score]" class="w-20 p-1 score-input"
                       data-row="${index}" data-col="1" autocomplete="off" inputmode="numeric" placeholder="3桁">
            </td>`;
        tableBody.appendChild(tr);
    }

    document.querySelector("#inputTable").addEventListener("input", function (e) {
        const t = e.target;
        if (!t.dataset) return;
        const row = parseInt(t.dataset.row||'0',10);
        const col = parseInt(t.dataset.col||'0',10);

        if (col === 0){
            const v = t.value.trim();
            if (/^\d{4}$/.test(v)){
                const next = document.querySelector(`[data-row="${row}"][data-col="1"]`);
                if (next) next.focus();
            }
        }
        if (col === 1){
            if (t.value.trim().length >= 3){
                const nextRow = row + 1;
                let next = document.querySelector(`[data-row="${nextRow}"][data-col="0"]`);
                if (!next){
                    addRow(nextRow);
                    next = document.querySelector(`[data-row="${nextRow}"][data-col="0"]`);
                }
                next && next.focus();
            }
        }
    });

    async function refreshExistingIds(){
        const params = new URLSearchParams({
            tournament_id: tSel.value, stage: sSel.value, game_number: gSel.value,
            shift: shiftSel.value || '', gender: genderSel.value || ''
        });
        try{
            const res = await fetch(`/scores/api/existing-ids?${params.toString()}`);
            if (!res.ok) return;
            window.__existingIds = await res.json();
        }catch(e){}
    }

    document.getElementById('scoreForm').addEventListener('submit', function(e){
        saveSelection();
        if (carryWrap.style.display !== 'none'){
            localStorage.setItem(carryKey(), carryChk.checked ? '1' : '0');
        }
        if (carrySemiWrap.style.display !== 'none'){
            localStorage.setItem(carryKeySemi(), carrySemiChk.checked ? '1' : '0');
        }

        let hasErr = false;
        const isNameMode = idType.value === 'name';
        const prevDigits = (window.__existingIds && window.__existingIds.prevDigits) || [];
        const existsThis = (window.__existingIds && window.__existingIds.existsThisGame) || [];

        const seenThis = new Set();

        document.querySelectorAll('.identifier-input').forEach((idEl)=>{
            const row = idEl.dataset.row;
            const scoreEl = document.querySelector(`[data-row="${row}"][data-col="1"]`);
            const idVal = (idEl.value||'').trim();
            const scVal = (scoreEl.value||'').trim();
            if (idVal === '' && scVal === '') return;

            const keyThis = (genderSel.value||'') + '#' + idVal;
            if (seenThis.has(keyThis)){
                idEl.classList.add('cell-error'); scoreEl.classList.add('cell-error'); hasErr=true;
            }else{
                seenThis.add(keyThis);
            }

            if (!isNameMode && parseInt(gSel.value,10) > 1){
                const digits = (idVal.match(/\d+/)||[''])[0];
                if (digits && prevDigits.length && !prevDigits.includes(digits)){
                    idEl.classList.add('cell-error'); hasErr=true;
                }
            }

            if (!isNameMode){
                const digits2 = (idVal.match(/\d+/)||[''])[0];
                if (digits2 && existsThis.includes(digits2)){
                    idEl.classList.add('cell-error'); scoreEl.classList.add('cell-error'); hasErr=true;
                }
            }
        });

        if (hasErr){
            e.preventDefault();
            alert('入力に問題があります（赤：未登録/重・複：同一G重複/既：同Gに既存）。修正してください。');
            return;
        }
    });

    openBtn.addEventListener('click', ()=>{
        saveSelection();
        const base = '/scores/result';
        const shiftsCSV = shiftSel.value ? shiftSel.value : '';
        const carryPre  = carryWrap.style.display === 'none' ? 0 : (localStorage.getItem(carryKey())==='1' ? 1 : 0);
        const carrySemi = carrySemiWrap.style.display === 'none' ? 0 : (carrySemiChk.checked ? 1 : 0); // ★追加

        const params = new URLSearchParams({
            tournament_id: tSel.value,
            stage:        sSel.value,
            upto_game:    gSel.value,
            shifts:       shiftsCSV,
            gender_filter: genderSel.value || '',
            border_type:  'rank',
            per_point:    '200',
            carry_prelim: String(carryPre),
            carry_semifinal: String(carrySemi), // ★準決勝の持ち込みを結果へ反映（決勝だけ）
        });
        window.open(`${base}?${params.toString()}`, '_blank');
    });

    if (carryChk){
        carryChk.addEventListener('change', ()=>{
            localStorage.setItem(carryKey(), carryChk.checked ? '1' : '0');
            saveSelection();
        });
    }
    if (carrySemiChk){
        carrySemiChk.addEventListener('change', ()=>{
            localStorage.setItem(carryKeySemi(), carrySemiChk.checked ? '1' : '0');
        });
    }

    // ▼ 既存：セレクタ変更時に既存ID再取得
    [tSel, sSel, gSel, shiftSel, idType, genderSel].forEach(el=>{
        el.addEventListener('change', ()=>{
            saveSelection();
            syncHidden();
            refillSummary();
            showCarryOption();
            refreshExistingIds();
        });
    });

    (function init(){
        const saved = loadSelection();
        if (saved && !@json((bool)$errors->any())){
            if ([...tSel.options].some(o=>o.value===saved.tournament_id)){
                tSel.value = saved.tournament_id;
            }
        }
        refillStages();

        if (!@json((bool)$errors->any())){
            const s2 = loadSelection() || {};
            if (s2.stage && [...sSel.options].some(o=>o.value===s2.stage)) sSel.value = s2.stage;
            if (s2.game_number && [...gSel.options].some(o=>o.value===String(s2.game_number))) gSel.value = String(s2.game_number);
            if (typeof s2.shift==='string' && [...shiftSel.options].some(o=>o.value===s2.shift)) shiftSel.value = s2.shift;
            if (typeof s2.gender==='string' && [...genderSel.options].some(o=>o.value===s2.gender)) genderSel.value = s2.gender;
            if (s2.identifier_type && [...idType.options].some(o=>o.value===s2.identifier_type)) idType.value = s2.identifier_type;
        }

        showCarryOption();
        syncHidden();
        refreshExistingIds(); // 初回取得
        refillSummary();
        saveSelection();
    })();
});
</script>
@endsection
