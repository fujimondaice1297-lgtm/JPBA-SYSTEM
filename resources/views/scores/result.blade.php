@extends('layouts.app')

@section('content')
@php
    $rows   = (isset($rankings) && is_iterable($rankings)) ? $rankings : [];
    $meta   = (isset($meta) && is_array($meta)) ? $meta : [];

    $stage          = request('stage', $meta['stage'] ?? '予選');
    $tournamentName = request('tournament_name', $tournament_name ?? ($meta['tournament']['name'] ?? '（大会名）'));
    $uptoGame       = (int)($meta['upto_game'] ?? request('upto_game', 1));
    $perPoint       = (int)($per_point ?? request('per_point', 200) ?? 200);

    $STAGE_ORDER = ['予選','準々決勝','準決勝','決勝'];
    $carryStages = array_values((array)($meta['carryStages'] ?? []));

    $headerBaseGames = $uptoGame;
    if (!empty($rows)) {
        $firstBk = (array)($rows[0]['breakdown'] ?? []);
        $headerBaseGames = min($uptoGame, count($firstBk[$stage] ?? []));
        foreach ($carryStages as $cs) {
            $headerBaseGames += count($firstBk[$cs] ?? []);
        }
        if ($headerBaseGames <= 0) {
            $headerBaseGames = $uptoGame;
        }
    }
    $baseTextHdr = $headerBaseGames . 'G × 200 = ' . number_format($headerBaseGames * $perPoint) . ' pin';

    $totals = [];
    foreach ($rows as $r) {
        $totals[] = (int)($r['total'] ?? $r['sum'] ?? 0);
    }
    $topTotal = $totals[0] ?? 0;

    $borderType  = request('border_type', 'rank');
    $borderValue = (int)request('border_value', 0);
    $borderIndex = null;
    $borderTotal = null;

    if ($borderType === 'rank' && $borderValue > 0) {
        $borderIndex = min(max($borderValue - 1, 0), max(count($rows) - 1, 0));
        $borderTotal = $totals[$borderIndex] ?? null;
    } elseif ($borderType === 'point' && $borderValue > 0) {
        $borderTotal = $borderValue;
    }

    $genderFilter = strtoupper((string)(request('gender_filter') ?: ''));

    $extractLicenseInfo = function(array $r): array {
        $candidates = [];

        if (isset($r['raw_ids']) && is_array($r['raw_ids'])) {
            foreach (['license', 'license_number', 'display_license', 'id', 'identifier'] as $k) {
                if (isset($r['raw_ids'][$k]) && $r['raw_ids'][$k] !== null) {
                    $candidates[] = (string)$r['raw_ids'][$k];
                }
            }
        }

        foreach (['license_number','license','display_license','license_digits','id','identifier','slug'] as $k) {
            if (array_key_exists($k, $r) && $r[$k] !== null) {
                $candidates[] = (string)$r[$k];
            }
        }

        foreach ($candidates as $s) {
            $s = trim($s);
            if ($s === '') {
                continue;
            }

            $digits = preg_replace('/\D+/', '', $s) ?: '';
            if ($digits === '') {
                continue;
            }

            $pad4 = strlen($digits) >= 4 ? substr($digits, -4) : str_pad($digits, 4, '0', STR_PAD_LEFT);
            $strip = ltrim($pad4, '0');
            if ($strip === '') {
                $strip = '0';
            }

            $sex = null;
            if (preg_match('/^[MF]/i', $s)) {
                $sex = strtoupper(substr($s, 0, 1));
            }

            return [
                'full'   => $s,
                'pad4'   => $pad4,
                'raw'    => $strip,
                'gender' => $sex,
            ];
        }

        return [
            'full'   => null,
            'pad4'   => null,
            'raw'    => null,
            'gender' => null,
        ];
    };

    $tokens = [];
    foreach ($rows as $r) {
        $info = $extractLicenseInfo((array)$r);
        if ($info['pad4']) {
            $tokens[] = $info['pad4'];
        }
        if ($info['raw']) {
            $tokens[] = $info['raw'];
        }
    }
    $tokens = array_values(array_unique(array_filter($tokens)));

    $profilesMap = [];
    $profilesAny = [];

    if (!empty($tokens)) {
        $query = \App\Models\ProBowler::query()
            ->select(['license_no', 'name_kanji', 'public_image_path'])
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $tok) {
                    $q->orWhere('license_no', 'like', '%' . $tok);
                }
            });

        if ($genderFilter !== '' && in_array($genderFilter, ['M', 'F'], true)) {
            $query->where('license_no', 'like', $genderFilter . '%');
        }

        $profileRows = $query->get();

        foreach ($profileRows as $bowler) {
            $licenseNo = trim((string)($bowler->license_no ?? ''));
            if ($licenseNo === '') {
                continue;
            }

            $sex = preg_match('/^[MF]/i', $licenseNo) ? strtoupper(substr($licenseNo, 0, 1)) : '';
            $digits = preg_replace('/\D+/', '', $licenseNo);
            if ($digits === '') {
                continue;
            }

            $pad4 = strlen($digits) >= 4 ? substr($digits, -4) : str_pad($digits, 4, '0', STR_PAD_LEFT);
            $raw4 = ltrim($pad4, '0');
            if ($raw4 === '') {
                $raw4 = '0';
            }

            $img = trim((string)($bowler->public_image_path ?? ''));
            $imgUrl = null;
            if ($img !== '') {
                $imgUrl = (preg_match('#^https?://#i', $img) || str_starts_with($img, '/'))
                    ? $img
                    : asset('storage/' . ltrim($img, '/'));
            }

            $payload = [
                'name' => (string)($bowler->name_kanji ?: $licenseNo),
                'portrait_url' => $imgUrl,
            ];

            if ($sex !== '') {
                $profilesMap[$sex . ':' . $pad4] = $profilesMap[$sex . ':' . $pad4] ?? $payload;
                $profilesMap[$sex . ':' . $raw4] = $profilesMap[$sex . ':' . $raw4] ?? $payload;
            }

            $profilesAny[$pad4] = $profilesAny[$pad4] ?? [];
            $profilesAny[$pad4][] = $payload;

            $profilesAny[$raw4] = $profilesAny[$raw4] ?? [];
            $profilesAny[$raw4][] = $payload;
        }
    }

    $profilesAnyUnique = [];
    foreach ($profilesAny as $token => $items) {
        $uniq = [];
        foreach ($items as $item) {
            $key = ($item['name'] ?? '') . '|' . ($item['portrait_url'] ?? '');
            $uniq[$key] = $item;
        }
        if (count($uniq) === 1) {
            $profilesAnyUnique[$token] = array_values($uniq)[0];
        }
    }

    $currentStageTotalGames = max($uptoGame, 1);
    foreach ($rows as $r) {
        $bk = (array)($r['breakdown'] ?? []);
        $currentStageTotalGames = max($currentStageTotalGames, count((array)($bk[$stage] ?? [])));
    }

    $isPublic = ((int)request('public', 0) === 1);

    $tournamentId = (int)(request('tournament_id') ?: ($meta['tournament_id'] ?? ($meta['tournament']['id'] ?? 0)));
    $snapshotQuery = array_filter([
        'gender' => $genderFilter !== '' ? $genderFilter : null,
        'shift'  => (string)(request('shifts') ?: ''),
    ], static fn ($v) => $v !== null && $v !== '');

    $stageOptions = [
        '予選' => '予選',
        '準々決勝' => '準々決勝',
        '準決勝' => '準決勝',
        '決勝' => '決勝',
        'ラウンドロビン' => 'ラウンドロビン',
        'トーナメント' => 'トーナメント',
        'シュートアウト' => 'シュートアウト',
    ];
@endphp

@if($isPublic)
<style>
    header, nav, .navbar, .topbar, .site-header, .app-header,
    .sidebar, .breadcrumb, .admin-menu, .auth-status, .login-state,
    .global-nav, .main-nav, .pwa-header, .layout-header {
        display: none !important;
        visibility: hidden !important;
    }
    body { padding-top: 0 !important; }
    .container, .container-fluid { margin-top: 0 !important; }
</style>
@endif

<style>
    .title-big { font-size: 1.6rem; font-weight: 800; }
    .subtitle  { color:#555; }
    .toolbar   { display:flex; gap:.6rem; align-items:center; margin:.7rem 0 1rem; flex-wrap:wrap; }
    .btn { background:#eee; padding:.35rem .6rem; border-radius:.4rem; border:1px solid #ddd; text-decoration:none; display:inline-block; }
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
    .tie    { color:#6b7280; font-size:.76rem; line-height:1.05; margin-top:2px; white-space:nowrap; }

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
    <div class="subtitle">
        同点時は、対象ゲームのスコア差が少ない選手を上位表示
    </div>

    @unless($isPublic)
    <div class="toolbar">
        <a class="btn" href="{{ url('/scores/input') }}">速報入力ページへ</a>
        @if($tournamentId > 0)
            <a class="btn" href="{{ route('tournaments.result_snapshots.index', ['tournament' => $tournamentId] + $snapshotQuery) }}">正式成績反映へ</a>
            <a class="btn" href="{{ route('tournaments.results.index', $tournamentId) }}">大会成績一覧へ</a>
        @endif
        <a class="btn" href="{{ request()->fullUrlWithQuery(['public'=>1]) }}">共有URL(公開)</a>

        <form method="GET" action="" style="display:inline-flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
            @foreach(request()->query() as $k => $v)
                @if(!in_array($k, ['border_type','border_value','upto_game','stage']))
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach

            <label>表示ステージ：
                <select name="stage">
                    @foreach($stageOptions as $stageValue => $stageLabel)
                        <option value="{{ $stageValue }}" {{ $stageValue === $stage ? 'selected' : '' }}>{{ $stageLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label>〇ゲーム目まで：
                <select name="upto_game">
                    @for($g = 1; $g <= max($currentStageTotalGames, 1); $g++)
                        <option value="{{ $g }}" {{ $g === $uptoGame ? 'selected' : '' }}>{{ $g }}</option>
                    @endfor
                </select>
            </label>

            <label>ボーダー種別：
                <select name="border_type">
                    <option value="rank" {{ request('border_type','rank') === 'rank' ? 'selected' : '' }}>順位（上位○名）</option>
                    <option value="point" {{ request('border_type') === 'point' ? 'selected' : '' }}>点数（○点以上）</option>
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

        $info = $extractLicenseInfo((array)$p);
        $digits4  = $info['pad4'];
        $digitsRaw = $info['raw'];
        $rowSex = strtoupper((string)($info['gender'] ?? ''));

        $prof = null;
        $trySexes = [];
        if ($rowSex !== '') {
            $trySexes[] = $rowSex;
        }

        $pGender = strtoupper((string)($p['gender'] ?? ''));
        if ($pGender !== '' && !in_array($pGender, $trySexes, true)) {
            $trySexes[] = $pGender;
        }

        if ($genderFilter !== '' && !in_array($genderFilter, $trySexes, true)) {
            $trySexes[] = $genderFilter;
        }

        foreach (['M', 'F'] as $sx) {
            if (!in_array($sx, $trySexes, true)) {
                $trySexes[] = $sx;
            }
        }

        if ($digits4 !== null) {
            foreach ($trySexes as $sx) {
                if (isset($profilesMap[$sx . ':' . $digits4])) {
                    $prof = $profilesMap[$sx . ':' . $digits4];
                    break;
                }
                if ($digitsRaw !== null && isset($profilesMap[$sx . ':' . $digitsRaw])) {
                    $prof = $profilesMap[$sx . ':' . $digitsRaw];
                    break;
                }
            }
        }

        if (!$prof && $digits4 !== null) {
            if (isset($profilesAnyUnique[$digits4])) {
                $prof = $profilesAnyUnique[$digits4];
            } elseif ($digitsRaw !== null && isset($profilesAnyUnique[$digitsRaw])) {
                $prof = $profilesAnyUnique[$digitsRaw];
            }
        }

        $rawIds = (array)($p['raw_ids'] ?? []);
        $fallbackName = (string)($rawIds['name'] ?? $p['name'] ?? $p['display_name'] ?? $p['display_license'] ?? $digits4 ?? '—');

        $name  = $prof['name'] ?? $fallbackName;
        $photo = $prof['portrait_url'] ?? null;

        $bk = (array)($p['breakdown'] ?? []);
        $stageLines = [];
        $baseGamesPerRow = 0;

        foreach ($STAGE_ORDER as $st) {
            if (!empty($bk[$st])) {
                $scores = array_values((array)$bk[$st]);
                $stageLines[$st] = implode('/', array_map(fn($x) => (int)$x, $scores));

                if ($st === $stage) {
                    $baseGamesPerRow += min($uptoGame, count($scores));
                } elseif (in_array($st, $carryStages, true)) {
                    $baseGamesPerRow += count($scores);
                }
            }
        }

        if ($baseGamesPerRow === 0 && !empty($p['games'])) {
            $baseGamesPerRow = min($uptoGame, count((array)$p['games']));
        }

        if ($baseGamesPerRow <= 0) {
            $baseGamesPerRow = max($uptoGame, 1);
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
        foreach ($STAGE_ORDER as $st) {
            if (isset($stageLines[$st])) {
                $stagesInline[] = "（{$st}：{$stageLines[$st]}）";
            }
        }

        $pass = false;
        if ($borderType === 'rank' && $borderValue > 0) {
            $pass = ($i <= $borderIndex);
        }
        if ($borderType === 'point' && $borderValue > 0) {
            $pass = ($sum >= $borderValue);
        }
    @endphp

    <div class="card {{ $pass ? 'rank-pass' : 'rank-fail' }}">
        <div class="row">
            <div class="meta">
                <div class="rank">{{ $rank }}位</div>
                @if($photo)
                    <img class="avatar" src="{{ $photo }}" alt="portrait">
                @else
                    <div class="avatar"></div>
                @endif
            </div>

            <div class="body">
                <div>
                    <div class="name">{{ $name }}</div>
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

    @if($borderType === 'rank' && $borderValue > 0 && $borderIndex !== null && $i === $borderIndex)
        <div class="border-line">通過ボーダーライン</div>
    @endif
@endforeach

@if(empty($rows))
    <div class="diff">データがありません。</div>
@endif
@endsection