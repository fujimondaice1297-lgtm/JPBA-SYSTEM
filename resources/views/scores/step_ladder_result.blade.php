@extends('layouts.app')

@section('content')
@php
    $meta = (array) ($stepLadder['meta'] ?? []);
    $seeds = array_values((array) ($stepLadder['seeds'] ?? []));
    $semifinal = (array) ($stepLadder['semifinal'] ?? []);
    $final = (array) ($stepLadder['final'] ?? []);
    $standings = array_values((array) ($stepLadder['standings'] ?? []));

    $uptoGame = (int) ($meta['upto_game'] ?? $upto_game ?? 1);
    $games = (int) ($meta['games'] ?? 2);

    $shiftValue = trim((string) request('shifts', $shifts ?? ''));
    $genderValue = trim((string) request('gender_filter', $gender_filter ?? ''));
    $isPublic = ((int) request('public', 0) === 1);
    $currentStage = trim((string) request('stage', '決勝'));

    $snapshotIndexUrl = request('tournament_id')
        ? route('tournaments.result_snapshots.index', ['tournament' => request('tournament_id')])
        : null;

    $hasPhotoUploadRoute = \Illuminate\Support\Facades\Route::has('scores.tournament_photos.store');

    $templatePath = public_path('images/step_ladder_tournament_bracket_template.png');
    $templateUrl = asset('images/step_ladder_tournament_bracket_template.png') . (is_file($templatePath) ? '?v=' . filemtime($templatePath) : '');

    $resolveDefaultPhotoUrl = function (array $player) {
        foreach ([
            'photo_url',
            'image_url',
            'profile_image_url',
            'profile_photo_url',
            'avatar_url',
            'photo',
            'image',
        ] as $key) {
            if (!empty($player[$key]) && is_string($player[$key])) {
                return $player[$key];
            }
        }

        return null;
    };

    $resolveTournamentPhotoUrl = function (array $player) use ($resolveDefaultPhotoUrl) {
        $tournamentId = (int) request('tournament_id');
        $participantKey = trim((string) ($player['participant_key'] ?? ''));

        if ($tournamentId > 0 && $participantKey !== '') {
            $safeBaseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $participantKey);
            $safeBaseName = trim((string) $safeBaseName, '_');

            if ($safeBaseName !== '') {
                $dir = public_path('tournament_images/' . $tournamentId);
                foreach (glob($dir . DIRECTORY_SEPARATOR . $safeBaseName . '.*') ?: [] as $path) {
                    if (is_file($path)) {
                        return asset('tournament_images/' . $tournamentId . '/' . basename($path)) . '?v=' . @filemtime($path);
                    }
                }
            }
        }

        return $resolveDefaultPhotoUrl($player);
    };

    $samePlayer = function ($a, $b): bool {
        if (!$a || !$b) {
            return false;
        }

        $keyA = trim((string) ($a['participant_key'] ?? ''));
        $keyB = trim((string) ($b['participant_key'] ?? ''));

        if ($keyA !== '' && $keyB !== '') {
            return $keyA === $keyB;
        }

        return trim((string) ($a['display_name'] ?? '')) === trim((string) ($b['display_name'] ?? ''));
    };

    $scoreText = function ($score) {
        return $score === null || $score === '' ? '' : (string) $score;
    };

    $seed1 = $seeds[0] ?? [];
    $seed2 = $seeds[1] ?? [];
    $seed3 = $seeds[2] ?? [];

    $seed1Name = trim((string) ($seed1['display_name'] ?? '—'));
    $seed2Name = trim((string) ($seed2['display_name'] ?? '—'));
    $seed3Name = trim((string) ($seed3['display_name'] ?? '—'));

    $seed1Photo = $resolveTournamentPhotoUrl($seed1);
    $seed2Photo = $resolveTournamentPhotoUrl($seed2);
    $seed3Photo = $resolveTournamentPhotoUrl($seed3);

    $semiWinner = (array) ($semifinal['winner'] ?? []);
    $semiLoser = (array) ($semifinal['loser'] ?? []);
    $finalWinner = (array) ($final['winner'] ?? []);
    $finalLoser = (array) ($final['loser'] ?? []);
    $finalBottom = (array) ($final['bottom'] ?? []);

    $semiTop = (array) ($semifinal['top'] ?? $seed2);
    $semiBottom = (array) ($semifinal['bottom'] ?? $seed3);
    $finalTop = (array) ($final['top'] ?? $seed1);

    $finalTopName = trim((string) ($finalTop['display_name'] ?? $seed1Name));
    $semiTopName = trim((string) ($semiTop['display_name'] ?? $seed2Name));
    $semiBottomName = trim((string) ($semiBottom['display_name'] ?? $seed3Name));

    $semiTopScoreText = $scoreText($semifinal['top_score'] ?? null);
    $semiBottomScoreText = $scoreText($semifinal['bottom_score'] ?? null);
    $finalTopScoreText = $scoreText($final['top_score'] ?? null);
    $finalBottomScoreText = $scoreText($final['bottom_score'] ?? null);

    $semiDone = (($semifinal['status'] ?? '') === 'done');
    $finalDone = (($final['status'] ?? '') === 'done');

    $isSemiTopWinner = $semiDone && $samePlayer($semiWinner, $semiTop);
    $isSemiBottomWinner = $semiDone && $samePlayer($semiWinner, $semiBottom);
    $isFinalTopWinner = $finalDone && $samePlayer($finalWinner, $finalTop);
    $isFinalBottomWinner = $finalDone && $samePlayer($finalWinner, $finalBottom);

    $championName = $finalDone
        ? trim((string) ($finalWinner['display_name'] ?? '未確定'))
        : '未確定';

    $runnerUp = $finalLoser ?: null;
    $thirdPlace = $semiLoser ?: ($seed3 ?: null);
@endphp

@if($isPublic)
<style>
    header, nav, .navbar, .topbar, .site-header, .app-header,
    .sidebar, .breadcrumb, .admin-menu, .auth-status, .login-state,
    .global-nav, .main-nav, .pwa-header, .layout-header {
        display: none !important;
        visibility: hidden !important;
    }

    body {
        padding-top: 0 !important;
    }

    .container, .container-fluid {
        margin-top: 0 !important;
    }
</style>
@endif

<style>
    .sl-card {
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #fff;
    }

    .sl-title {
        font-size: 1.5rem;
        font-weight: 800;
    }

    .sl-sub {
        color: #6b7280;
        margin-top: .25rem;
        margin-bottom: 1rem;
    }

    .sl-toolbar {
        display: flex;
        gap: .6rem;
        align-items: center;
        flex-wrap: wrap;
        margin: 0 0 1rem;
    }

    .sl-photo-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(220px, 1fr));
        gap: 12px;
        margin-bottom: 1rem;
    }

    .sl-photo-card {
        border: 1px solid #8b8b8b;
        background: #fff;
    }

    .sl-photo-head {
        padding: 6px 8px;
        font-weight: 700;
        border-bottom: 1px solid #bcbcbc;
        background: #fff;
    }

    .sl-photo-body {
        min-height: 210px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f5f5f5;
        color: #999;
        font-size: .9rem;
        overflow: hidden;
    }

    .sl-photo-body img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .sl-photo-name {
        padding: 8px 10px 4px;
        text-align: center;
        font-weight: 700;
        background: #fff;
        font-size: 1.15rem;
    }

    .sl-photo-actions {
        padding: 0 10px 10px;
        display: flex;
        flex-direction: column;
        gap: .45rem;
        align-items: center;
    }

    .sl-photo-upload-form {
        width: 100%;
        max-width: 210px;
    }

    .sl-photo-upload-form input[type="file"] {
        font-size: .8rem;
    }

    .sl-photo-hint {
        color: #6b7280;
        font-size: .88rem;
        margin-top: -.2rem;
        margin-bottom: 1rem;
    }

    .sl-bracket-wrap {
        width: 100%;
        overflow-x: hidden;
        padding-bottom: 8px;
        margin-bottom: 1rem;
        background: #fff;
    }

    .sl-bracket-box {
        width: 100%;
        max-width: 100%;
        background: #fff;
        padding: 0;
    }

    .sl-template-svg {
        display: block;
        width: 100%;
        max-width: 100%;
        height: auto;
        background: #fff;
    }

    .sl-note {
        color: #374151;
        font-size: .92rem;
        margin-top: .5rem;
    }

    .sl-table-wrap {
        overflow-x: auto;
    }

    .sl-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }

    .sl-table th,
    .sl-table td {
        border: 1px solid #d1d5db;
        padding: .5rem;
        text-align: center;
    }

    .sl-table th {
        background: #f3f4f6;
    }

    .sl-left {
        text-align: left !important;
    }

    @media (max-width: 991.98px) {
        .sl-photo-grid {
            grid-template-columns: 1fr;
        }

        .sl-bracket-wrap {
            overflow-x: auto;
        }

        .sl-bracket-box {
            min-width: 900px;
        }
    }
</style>

<div class="sl-card">
    <div class="sl-title">{{ $tournament_name }} / 決勝ステップラダー</div>
    <div class="sl-sub">テンプレート画像を土台にして、名前・点数・勝者線だけを同じ座標系で重ねています。</div>

    @unless($isPublic)
        <div class="sl-toolbar">
            <a class="btn btn-outline-secondary" href="{{ url('/scores/input') }}">速報入力ページへ</a>

            @if($snapshotIndexUrl)
                <a class="btn btn-outline-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
            @endif

            <a class="btn btn-outline-secondary" href="{{ route('scores.result', array_filter([
                'tournament_id' => request('tournament_id'),
                'stage' => $currentStage,
                'upto_game' => $uptoGame,
                'shifts' => $shiftValue,
                'gender_filter' => $genderValue,
                'public' => 1,
            ])) }}">共有URL(公開)</a>

            <form method="GET" action="" style="display:inline-flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="tournament_id" value="{{ request('tournament_id') }}">
                <input type="hidden" name="stage" value="{{ $currentStage }}">
                @if($shiftValue !== '')<input type="hidden" name="shifts" value="{{ $shiftValue }}">@endif
                @if($genderValue !== '')<input type="hidden" name="gender_filter" value="{{ $genderValue }}">@endif

                <label>〇ゲーム目まで：
                    <select name="upto_game">
                        @for($g = 1; $g <= max($games, 1); $g++)
                            <option value="{{ $g }}" {{ $g === $uptoGame ? 'selected' : '' }}>{{ $g }}</option>
                        @endfor
                    </select>
                </label>

                <button class="btn btn-primary">再表示</button>
            </form>
        </div>
    @endunless

    @if(!empty($stepLadder['missing_seed_snapshot']))
        <div class="alert alert-warning mb-0">
            ラウンドロビン最終成績の current snapshot（round_robin_total）が見つかりません。<br>
            先に正式成績反映ページで「ラウンドロビン最終成績」を反映してください。
        </div>
    @else
        <div class="sl-photo-grid">
            <div class="sl-photo-card">
                <div class="sl-photo-head">1位通過</div>
                <div class="sl-photo-body">
                    @if($seed1Photo)
                        <img src="{{ $seed1Photo }}" alt="{{ $seed1Name }}">
                    @else
                        <span>PHOTO</span>
                    @endif
                </div>
                <div class="sl-photo-name">{{ $seed1Name }}</div>

                @unless($isPublic)
                    <div class="sl-photo-actions">
                        @if($hasPhotoUploadRoute)
                            <form method="POST" action="{{ route('scores.tournament_photos.store') }}" enctype="multipart/form-data" class="sl-photo-upload-form">
                                @csrf
                                <input type="hidden" name="tournament_id" value="{{ request('tournament_id') }}">
                                <input type="hidden" name="participant_key" value="{{ $seed1['participant_key'] ?? '' }}">
                                <input type="hidden" name="display_name" value="{{ $seed1Name }}">
                                <input type="file" name="photo" class="form-control form-control-sm" accept="image/*" required>
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100 mt-2">大会写真を登録</button>
                            </form>
                        @endif
                    </div>
                @endunless
            </div>

            <div class="sl-photo-card">
                <div class="sl-photo-head">2位通過</div>
                <div class="sl-photo-body">
                    @if($seed2Photo)
                        <img src="{{ $seed2Photo }}" alt="{{ $seed2Name }}">
                    @else
                        <span>PHOTO</span>
                    @endif
                </div>
                <div class="sl-photo-name">{{ $seed2Name }}</div>

                @unless($isPublic)
                    <div class="sl-photo-actions">
                        @if($hasPhotoUploadRoute)
                            <form method="POST" action="{{ route('scores.tournament_photos.store') }}" enctype="multipart/form-data" class="sl-photo-upload-form">
                                @csrf
                                <input type="hidden" name="tournament_id" value="{{ request('tournament_id') }}">
                                <input type="hidden" name="participant_key" value="{{ $seed2['participant_key'] ?? '' }}">
                                <input type="hidden" name="display_name" value="{{ $seed2Name }}">
                                <input type="file" name="photo" class="form-control form-control-sm" accept="image/*" required>
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100 mt-2">大会写真を登録</button>
                            </form>
                        @endif
                    </div>
                @endunless
            </div>

            <div class="sl-photo-card">
                <div class="sl-photo-head">3位通過</div>
                <div class="sl-photo-body">
                    @if($seed3Photo)
                        <img src="{{ $seed3Photo }}" alt="{{ $seed3Name }}">
                    @else
                        <span>PHOTO</span>
                    @endif
                </div>
                <div class="sl-photo-name">{{ $seed3Name }}</div>

                @unless($isPublic)
                    <div class="sl-photo-actions">
                        @if($hasPhotoUploadRoute)
                            <form method="POST" action="{{ route('scores.tournament_photos.store') }}" enctype="multipart/form-data" class="sl-photo-upload-form">
                                @csrf
                                <input type="hidden" name="tournament_id" value="{{ request('tournament_id') }}">
                                <input type="hidden" name="participant_key" value="{{ $seed3['participant_key'] ?? '' }}">
                                <input type="hidden" name="display_name" value="{{ $seed3Name }}">
                                <input type="file" name="photo" class="form-control form-control-sm" accept="image/*" required>
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100 mt-2">大会写真を登録</button>
                            </form>
                        @endif
                    </div>
                @endunless
            </div>
        </div>

        <div class="sl-photo-hint">
            ここで登録した大会専用写真は、ラウンドロビンと決勝ステップラダーで共通表示します。
        </div>

        <div class="sl-bracket-wrap">
            <div class="sl-bracket-box">
                <svg class="sl-template-svg"
                     viewBox="0 0 1672 941"
                     xmlns="http://www.w3.org/2000/svg"
                     preserveAspectRatio="xMinYMin meet"
                     aria-label="決勝ステップラダー">

                    <image href="{{ $templateUrl }}"
                           x="0"
                           y="0"
                           width="1672"
                           height="941"
                           preserveAspectRatio="xMinYMin meet" />

                    {{-- 勝者線：1回戦 --}}
                    @if($semiDone && $isSemiTopWinner)
                        <line x1="854" y1="506" x2="1105" y2="506" stroke="#ef1111" stroke-width="8" stroke-linecap="butt" />
                    @elseif($semiDone && $isSemiBottomWinner)
                        <line x1="753" y1="695" x2="988" y2="695" stroke="#ef1111" stroke-width="8" stroke-linecap="butt" />
                        <line x1="988" y1="506" x2="988" y2="695" stroke="#ef1111" stroke-width="8" stroke-linecap="butt" />
                        <line x1="988" y1="506" x2="1105" y2="506" stroke="#ef1111" stroke-width="8" stroke-linecap="butt" />
                    @endif

                    {{-- 勝者線：優勝決定戦 --}}
                    @if($finalDone && $isFinalTopWinner)
                        <line x1="959" y1="330" x2="1237" y2="330" stroke="#ef1111" stroke-width="8" stroke-linecap="butt" />
                    @elseif($finalDone && $isFinalBottomWinner)
                        <line x1="1105" y1="506" x2="1105" y2="330" stroke="#ef1111" stroke-width="8" stroke-linecap="butt" />
                        <line x1="1105" y1="330" x2="1237" y2="330" stroke="#ef1111" stroke-width="8" stroke-linecap="butt" />
                    @endif

                    {{-- 名前：1位通過 --}}
                    <text x="815" y="331"
                          text-anchor="middle"
                          dominant-baseline="middle"
                          font-size="38"
                          font-weight="800"
                          fill="#111111"
                          font-family="sans-serif">{{ $finalTopName }}</text>

                    {{-- 名前：2位通過 --}}
                    <text x="715" y="507"
                          text-anchor="middle"
                          dominant-baseline="middle"
                          font-size="38"
                          font-weight="800"
                          fill="#111111"
                          font-family="sans-serif">{{ $semiTopName }}</text>

                    {{-- 名前：3位通過 --}}
                    <text x="607" y="695"
                          text-anchor="middle"
                          dominant-baseline="middle"
                          font-size="38"
                          font-weight="800"
                          fill="#111111"
                          font-family="sans-serif">{{ $semiBottomName }}</text>

                    {{-- 1回戦スコア：2位通過 --}}
                    @if($semiTopScoreText !== '')
                        <text x="920" y="486"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            font-size="32"
                            font-weight="800"
                            fill="{{ $isSemiTopWinner ? '#ef1111' : '#111111' }}"
                            font-family="sans-serif">{{ $semiTopScoreText }}</text>
                    @endif

                    {{-- 1回戦スコア：3位通過 --}}
                    @if($semiBottomScoreText !== '')
                        <text x="920" y="733"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            font-size="32"
                            font-weight="800"
                            fill="{{ $isSemiBottomWinner ? '#ef1111' : '#111111' }}"
                            font-family="sans-serif">{{ $semiBottomScoreText }}</text>
                    @endif

                    {{-- 優勝決定戦スコア：1位通過 --}}
                    @if($finalTopScoreText !== '')
                        <text x="1088" y="286"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            font-size="32"
                            font-weight="800"
                            fill="{{ $isFinalTopWinner ? '#ef1111' : '#111111' }}"
                            font-family="sans-serif">{{ $finalTopScoreText }}</text>
                    @endif

                    {{-- 優勝決定戦スコア：1回戦勝者 --}}
                    @if($finalBottomScoreText !== '')
                        <text x="1088" y="548"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            font-size="32"
                            font-weight="800"
                            fill="{{ $isFinalBottomWinner ? '#ef1111' : '#111111' }}"
                            font-family="sans-serif">{{ $finalBottomScoreText }}</text>
                    @endif

                    {{-- 優勝者名 --}}
                    <text x="1419" y="362"
                          text-anchor="middle"
                          dominant-baseline="middle"
                          font-size="42"
                          font-weight="800"
                          fill="#111111"
                          font-family="sans-serif">{{ $championName }}</text>
                </svg>
            </div>
        </div>

        <div class="sl-note">
            @if($finalDone)
                優勝：{{ $finalWinner['display_name'] ?? '—' }} ／ 準優勝：{{ $runnerUp['display_name'] ?? '—' }} ／ 3位：{{ $thirdPlace['display_name'] ?? '—' }}
            @elseif($semiDone)
                1回戦は確定済みです。優勝決定戦の入力待ちです。
            @else
                1回戦は未確定です。
            @endif
        </div>

        <div class="sl-table-wrap mt-4">
            <h4>現在の最終順位</h4>
            <table class="sl-table">
                <thead>
                    <tr>
                        <th>順位</th>
                        <th class="sl-left">選手名</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($standings as $row)
                        <tr>
                            <td>{{ $row['rank'] ?? '—' }}</td>
                            <td class="sl-left">{{ $row['player']['display_name'] ?? '—' }}</td>
                            <td>{{ $row['reason'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection