@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Str;

    $rows   = (isset($rankings) && is_iterable($rankings)) ? $rankings : [];
    $meta   = (isset($meta) && is_array($meta)) ? $meta : [];

    $stage          = request('stage', $meta['stage'] ?? '予選');
    $tournamentName = request('tournament_name', $tournament_name ?? ($meta['tournament']['name'] ?? '（大会名）'));
    $uptoGame       = (int)($meta['upto_game'] ?? request('upto_game', 1));

    $perPoint = 200;

    $STAGE_ORDER   = ['予選','準々決勝','準決勝','決勝'];
    $carryStages   = array_values((array)($meta['carryStages']   ?? []));
    $includeStages = array_values((array)($meta['includeStages'] ?? []));

    $headerBaseGames = $uptoGame;
    if (!empty($rows)) {
        $firstBk = (array)($rows[0]['breakdown'] ?? []);
        $headerBaseGames = min($uptoGame, count($firstBk[$stage] ?? []));
        foreach ($carryStages as $cs) $headerBaseGames += count($firstBk[$cs] ?? []);
        if ($headerBaseGames <= 0) $headerBaseGames = $uptoGame;
    }
    $baseTextHdr = $headerBaseGames.'G × 200 = '.number_format($headerBaseGames*$perPoint).' pin';

    $totals = [];
    foreach ($rows as $r) $totals[] = (int)($r['total'] ?? $r['sum'] ?? 0);
    $topTotal  = $totals[0] ?? 0;

    $borderType  = request('border_type','rank');
    $borderValue = (int)request('border_value', 0);
    $borderIndex = null;
    $borderTotal = null;

    if ($borderType === 'rank' && $borderValue > 0) {
        $borderIndex = min(max($borderValue - 1, 0), max(count($rows)-1, 0));
        $borderTotal = $totals[$borderIndex] ?? null;
    } elseif ($borderType === 'point' && $borderValue > 0) {
        $borderTotal = $borderValue;
    }

    $genderFilter = request('gender_filter') ?: null;

    // 4桁と非ゼロ埋めのペアを返す
    $extractDigitsPair = function(array $r): array {
        foreach (['license_digits','license_number','license','display_license','id','identifier','slug'] as $k) {
            if (!array_key_exists($k,$r)) continue;
            $s = (string)$r[$k];
            if (preg_match('/(\d{3,6})/', $s, $m)) {
                $raw   = $m[1];
                $pad4  = strlen($raw) >= 4 ? substr($raw, -4) : str_pad($raw, 4, '0', STR_PAD_LEFT);
                $strip = ltrim($pad4, '0'); if ($strip === '') $strip = '0';
                return [$pad4, $strip];
            }
        }
        return [null, null];
    };

    // 事前収集（M/F 両対応）
    $digitsM_pad=[]; $digitsM_raw=[];
    $digitsF_pad=[]; $digitsF_raw=[];
    foreach ($rows as $r) {
        [$pad,$raw] = $extractDigitsPair((array)$r);
        if ($pad === null) continue;
        $g = strtoupper((string)($r['gender'] ?? ''));
        if     ($g === 'M') { $digitsM_pad[]=$pad; $digitsM_raw[]=$raw; }
        elseif ($g === 'F') { $digitsF_pad[]=$pad; $digitsF_raw[]=$raw; }
        else { // 性別不明は両面へ
            $digitsM_pad[]=$pad; $digitsM_raw[]=$raw;
            $digitsF_pad[]=$pad; $digitsF_raw[]=$raw;
        }
    }
    $digitsM_pad = array_values(array_unique($digitsM_pad));
    $digitsM_raw = array_values(array_unique($digitsM_raw));
    $digitsF_pad = array_values(array_unique($digitsF_pad));
    $digitsF_raw = array_values(array_unique($digitsF_raw));

    // まとめて解決 → M:xxxx / F:xxxx キーでキャッシュ
    $profilesMap = [];
    $resolveBatchSafe = function(array $tokens, ?string $sex) {
        try {
            return app(\App\Services\ProfileService::class)->resolveBatch($tokens, $sex) ?: [];
        } catch (\Throwable $e) { return []; }
    };
    $attach = function(array $res, string $sex) use (&$profilesMap){
        foreach ($res as $k=>$v) { $profilesMap[strtoupper($sex).':'.$k] = $v; }
    };

    if ($genderFilter === 'M') {
        $attach($resolveBatchSafe($digitsM_pad,'M'),'M');
        $attach($resolveBatchSafe($digitsM_raw,'M'),'M');
    } elseif ($genderFilter === 'F') {
        $attach($resolveBatchSafe($digitsF_pad,'F'),'F');
        $attach($resolveBatchSafe($digitsF_raw,'F'),'F');
    } else {
        $attach($resolveBatchSafe($digitsM_pad,'M'),'M');
        $attach($resolveBatchSafe($digitsM_raw,'M'),'M');
        $attach($resolveBatchSafe($digitsF_pad,'F'),'F');
        $attach($resolveBatchSafe($digitsF_raw,'F'),'F');
    }

    $isPublic = ((int)request('public', 0) === 1);
@endphp
{{-- 共有URL（一般公開）では、上部ナビや管理メニュー等を全面非表示にする --}}
@if($isPublic)
<style>
    /* 共通でありがちなヘッダ/ナビ/サイドバーのクラスや要素を幅広く潰す */
    header, nav, .navbar, .topbar, .site-header, .app-header,
    .sidebar, .breadcrumb, .admin-menu, .auth-status, .login-state,
    .global-nav, .main-nav, .pwa-header, .layout-header {
        display: none !important;
        visibility: hidden !important;
    }
    /* レイアウトの余白が残る場合に備えて上余白を詰める */
    body { padding-top: 0 !important; }
    .container, .container-fluid { margin-top: 0 !important; }
</style>
@endif
<style>
    .title-big { font-size: 1.6rem; font-weight: 800; }
    .subtitle  { color:#555; }
    .toolbar   { display:flex; gap:.6rem; align-items:center; margin:.7rem 0 1rem; flex-wrap:wrap; }
    .btn { background:#eee; padding:.35rem .6rem; border-radius:.4rem; border:1px solid #ddd; }
    .btn-primary { background:#3b82f6; color:#fff; border-color:#2f6bdc; }

    :root { --rightw: 260px; }

    .card { border:1px solid #ddd; border-radius:10px; margin:.34rem 0; padding:.32rem .55rem; }
    .card .row {
        position: relative;
        display:flex; align-items:center; gap:10px;
        min-height: 44px;
        padding-right: var(--rightw);
    }
    .rank-pass { background:#fff8d9; }
    .rank-fail { background:#fff; }
    .border-line { background:#2563eb; color:#fff; text-align:center; padding:6px 10px; border-radius:8px; margin:.5rem 0; font-weight:700; }

    .avatar { width:34px; height:34px; aspect-ratio:1/1; border-radius:50%; object-fit:cover; object-position:center; background:#f3f4f6; border:1px solid #e5e7eb; }
    .meta   { display:flex; align-items:center; gap:10px; min-width:5.4rem; }
    .rank   { font-weight:800; }

    .body   { display:flex; flex:1; align-items:center; gap:10px; min-width:0; }
    .name   { font-weight:700; line-height:1.05; }
    .lic    { color:#666; font-size:.86rem; line-height:1.05; margin-top:1px; white-space:nowrap; }

    .stages { color:#6b7280; font-size:.84rem; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .stages .sep { padding:0 .35rem; color:#9ca3af; }

    .right  {
        position:absolute; top:6px; right:10px;
        width: var(--rightw);
        display:flex; flex-direction:column; align-items:flex-end; gap:.15rem;
        white-space:nowrap;
    }
    .totline { display:flex; align-items:center; gap:.6rem; }
    .sum  { font-weight:800; }
    .base { font-weight:800; }
    .diff { color:#6b7280; font-size:.84rem; }

    @media (max-width: 640px) {
        :root { --rightw: 0px; }
        .card .row { padding-right: 0; flex-wrap:wrap; gap:8px; min-height:auto; }
        .meta { min-width:auto; }
        .avatar { width:32px; height:32px; }
        .body { flex:1 1 60%; min-width:240px; }
        .right { position:static; width:auto; margin-left:0; align-items:flex-start; }
    }
</style>

<div class="mb-2">
    <div class="title-big">速報ランキング</div>
    <div class="title-big" style="margin-top:.2rem;">{{ $tournamentName }}</div>
    <div class="subtitle">
        ステージ：{{ $stage }} ／ 集計対象：{{ $uptoGame }}G まで　／　基準：{{ $baseTextHdr }}
    </div>

    @unless($isPublic)
    <div class="toolbar">
        <a class="btn" href="{{ url('/scores/input') }}">入力ページへ戻る</a>
        <a class="btn" href="{{ request()->fullUrlWithQuery(['public'=>1]) }}">共有URL(公開)</a>

        <form method="GET" action="" style="display:inline-flex; gap:.5rem; align-items:center;">
            @foreach(request()->query() as $k=>$v)
                @if(!in_array($k,['border_type','border_value']))
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach
            <label>ボーダー種別：
                <select name="border_type">
                    <option value="rank"  {{ request('border_type','rank')==='rank' ? 'selected':'' }}>順位（上位○名）</option>
                    <option value="point" {{ request('border_type')==='point' ? 'selected':'' }}>点数（○点以上）</option>
                </select>
            </label>
            <label>ボーダー値：
                <input type="number" name="border_value" style="width:7rem" placeholder="例：16" value="{{ (int)request('border_value',0) ?: '' }}">
            </label>
            <button class="btn btn-primary">再表示</button>
        </form>
    </div>
    @endunless
</div>

@foreach($rows as $i => $p)
    @php
        $rank = $i + 1;
        $sum  = (int)($p['total'] ?? $p['sum'] ?? 0);

        [$digits4, $digitsRaw] = $extractDigitsPair((array)$p);
        $gkey   = strtoupper((string)($p['gender'] ?? ''));

        // キャッシュ優先（性別不明は M→F の順）
        $prof = null;
        if ($digits4 !== null) {
            $cands = [];
            if ($gkey) {
                $cands[] = $gkey.':'.$digits4;
                $cands[] = $gkey.':'.$digitsRaw;
            } else {
                $cands[] = 'M:'.$digits4;  $cands[] = 'M:'.$digitsRaw;
                $cands[] = 'F:'.$digits4;  $cands[] = 'F:'.$digitsRaw;
            }
            foreach ($cands as $ck) { if (isset($profilesMap[$ck])) { $prof = $profilesMap[$ck]; break; } }
        }

        // フォールバック：行単位問い合わせ
        if (!$prof && $digits4 !== null) {
            $trySexes = $gkey ? [$gkey] : ['M','F'];
            foreach ($trySexes as $sx) {
                $svc = app(\App\Services\ProfileService::class);
                try {
                    $res = $svc->resolveBatch([$digits4, $digitsRaw], $sx) ?: [];
                } catch (\Throwable $e) { $res = []; }
                foreach ($res as $k=>$v) {
                    $profilesMap[$sx.':'.$k] = $v;
                }
                if (isset($profilesMap[$sx.':'.$digits4])) { $prof = $profilesMap[$sx.':'.$digits4]; break; }
                if (isset($profilesMap[$sx.':'.$digitsRaw])) { $prof = $profilesMap[$sx.':'.$digitsRaw]; break; }
            }
        }

        $name   = $prof['name'] ?? null;
        $photo  = $prof['portrait_url'] ?? null;

        $bk = (array)($p['breakdown'] ?? []);
        $stageLines = [];
        $baseGamesPerRow = 0;
        foreach ($STAGE_ORDER as $st) {
            if (!empty($bk[$st])) {
                $scores = array_values((array)$bk[$st]);
                $stageLines[$st] = implode('/', array_map(fn($x)=>(int)$x, $scores));
                if     ($st === $stage)                         $baseGamesPerRow += min($uptoGame, count($scores));
                elseif (in_array($st, $carryStages, true))      $baseGamesPerRow += count($scores);
            }
        }
        if ($baseGamesPerRow === 0 && !empty($p['games'])) {
            $baseGamesPerRow = min($uptoGame, count((array)$p['games']));
        }

        $baseOver = $sum - ($baseGamesPerRow * $perPoint);
        $baseTextInline = sprintf('%+d', $baseOver);

        $diffTop    = $sum - $topTotal;
        $diffBorder = ($borderTotal !== null) ? ($sum - $borderTotal) : null;
        if ($borderType === 'rank' && $borderValue > 0 && $borderIndex !== null) {
            $diffText = ($i <= $borderIndex)
                ? sprintf('%+d トップとの差', $diffTop)
                : sprintf('%+d ボーダーとの差', $diffBorder ?? 0);
        } else {
            $diffText = sprintf('%+d トップとの差', $diffTop);
        }

        $stagesInline = [];
        foreach ($STAGE_ORDER as $st) if (isset($stageLines[$st])) $stagesInline[] = "（{$st}：{$stageLines[$st]}）";

        $pass = false;
        if ($borderType==='rank'  && $borderValue>0) $pass = ($i <= $borderIndex);
        if ($borderType==='point' && $borderValue>0) $pass = ($sum >= $borderValue);
    @endphp

    <div class="card {{ $pass ? 'rank-pass':'rank-fail' }}">
        <div class="row">
            <div class="meta">
                <div class="rank">{{ $rank }}位</div>
                @if($photo)<img class="avatar" src="{{ $photo }}" alt="portrait">@else<div class="avatar"></div>@endif
            </div>

            <div class="body">
                <div>
                    <div class="name">{{ $name ?? '—' }}</div>
                    <div class="lic">Lic: {{ $digits4 ?? ($p['display_license'] ?? '—') }}</div>
                </div>
                @if($stagesInline)
                    <div class="stages">{!! implode('<span class="sep">｜</span>', array_map('e', $stagesInline)) !!}</div>
                @endif
            </div>

            <div class="right">
                <div class="totline">
                    <span class="sum">{{ number_format($sum) }} pin</span>
                    <span class="base">{{ $baseTextInline }}</span>
                </div>
                <div class="diff">{{ $diffText }}</div>
            </div>
        </div>
    </div>

    @if($borderType==='rank' && $borderValue>0 && $borderIndex!==null && $i === $borderIndex)
        <div class="border-line">通過ボーダーライン</div>
    @endif
@endforeach

@if(empty($rows))
    <div class="diff">データがありません。</div>
@endif
@endsection
