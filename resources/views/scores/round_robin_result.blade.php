@extends('layouts.app')

@section('content')
@php
    $meta = (array) ($rr['meta'] ?? []);
    $players = (array) ($rr['players'] ?? []);
    $matrix = (array) ($rr['matrix'] ?? []);
    $uptoGame = (int) ($meta['upto_game'] ?? $upto_game ?? 1);
    $roundRobinGames = (int) ($meta['round_robin_games'] ?? 8);
    $winBonus = (int) ($meta['win_bonus'] ?? 30);
    $tieBonus = (int) ($meta['tie_bonus'] ?? 15);
    $shiftValue = trim((string) request('shifts', $shifts ?? ''));
    $genderValue = trim((string) request('gender_filter', $gender_filter ?? ''));
    $isPublic = ((int) request('public', 0) === 1);
    $snapshotIndexUrl = request('tournament_id')
        ? route('tournaments.result_snapshots.index', ['tournament' => request('tournament_id')])
        : null;

    $stepLadderEntrants = array_values(array_slice($players, 0, 3));

    $stepLadderLabels = [
        0 => '1位通過',
        1 => '2位通過',
        2 => '3位通過',
    ];

    $resolveDefaultPhotoUrl = function (array $player) {
        $candidateKeys = [
            'photo_url',
            'image_url',
            'profile_image_url',
            'profile_photo_url',
            'avatar_url',
            'photo',
            'image',
        ];

        foreach ($candidateKeys as $key) {
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
    .rr-toolbar { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; margin:0 0 1rem; }
    .rr-card { border:1px solid #ddd; border-radius:10px; padding:1rem; margin-bottom:1rem; background:#fff; }
    .rr-title { font-size:1.5rem; font-weight:800; }
    .rr-sub { color:#6b7280; margin-top:.25rem; }
    .rr-table-wrap { overflow-x:auto; }
    .rr-table { width:100%; border-collapse:collapse; min-width:980px; }
    .rr-table th, .rr-table td { border:1px solid #d1d5db; padding:.45rem .5rem; text-align:center; vertical-align:middle; }
    .rr-table th { background:#f3f4f6; font-weight:700; white-space:nowrap; }
    .rr-left { text-align:left !important; white-space:nowrap; }
    .rr-chip { display:inline-block; min-width:1.8rem; padding:.15rem .45rem; border-radius:999px; background:#eff6ff; margin:.1rem; font-size:.82rem; }
    .rr-mini { font-size:.82rem; color:#6b7280; }
    .rr-cell-line { display:block; line-height:1.35; }
    .rr-win { color:#065f46; font-weight:700; }
    .rr-loss { color:#991b1b; font-weight:700; }
    .rr-tie { color:#92400e; font-weight:700; }
    .rr-nav { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1rem; }
    .rr-nav a { text-decoration:none; padding:.35rem .6rem; border:1px solid #d1d5db; border-radius:.5rem; background:#f9fafb; }
    .rr-note { color:#374151; font-size:.92rem; margin-top:.5rem; }

    .rr-step-qualifiers {
        margin: 1.25rem 0 1.5rem;
    }

    .rr-step-qualifiers-title {
        font-size: 1.2rem;
        font-weight: 800;
        margin-bottom: .8rem;
    }

    .rr-step-qualifiers-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(220px, 1fr));
        gap: .75rem;
    }

    .rr-step-entrant-card {
        border: 1px solid #8f8f8f;
        background: #fff;
    }

    .rr-step-entrant-head {
        padding: .45rem .55rem;
        border-bottom: 1px solid #8f8f8f;
        font-weight: 700;
        font-size: .95rem;
        text-align: left;
        background: #fff;
    }

    .rr-step-entrant-photo {
        width: 100%;
        aspect-ratio: 3 / 4;
        background: #efefef;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 1.05rem;
        overflow: hidden;
    }

    .rr-step-entrant-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .rr-step-entrant-name {
        padding: .6rem .75rem .35rem;
        border-top: 1px solid #d1d5db;
        text-align: center;
        font-weight: 700;
        font-size: 1.25rem;
        line-height: 1.4;
        background: #fff;
    }

    .rr-step-entrant-actions {
        padding: 0 .75rem .75rem;
        display: flex;
        flex-direction: column;
        gap: .45rem;
        align-items: center;
    }

    .rr-step-entrant-actions .btn {
        font-size: .85rem;
    }

    .rr-step-upload-form {
        width: 100%;
        max-width: 210px;
    }

    .rr-step-upload-form input[type="file"] {
        font-size: .8rem;
    }

    .rr-step-photo-hint {
        color: #6b7280;
        font-size: .88rem;
        margin-top: .6rem;
    }

    @media (max-width: 991.98px) {
        .rr-step-qualifiers-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="rr-card">
    <div class="rr-title">{{ $tournament_name }} / ラウンドロビン</div>
    <div class="rr-sub">
        集計対象：{{ $uptoGame }}G まで ／ 進出人数：{{ (int) ($meta['qualifier_count'] ?? 0) }}名 ／ 勝ち {{ $winBonus }}P ／ 引き分け {{ $tieBonus }}P
    </div>

    @unless($isPublic)
    <div class="rr-toolbar">
        <a class="btn btn-outline-secondary" href="{{ url('/scores/input') }}">速報入力ページへ</a>

        @if($snapshotIndexUrl)
            <a class="btn btn-outline-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
        @endif

        <a class="btn btn-outline-secondary" href="{{ route('scores.result', array_filter([
            'tournament_id' => request('tournament_id'),
            'stage' => 'ラウンドロビン',
            'upto_game' => $uptoGame,
            'shifts' => $shiftValue,
            'gender_filter' => $genderValue,
            'public' => 1,
        ])) }}">共有URL(公開)</a>

        <form method="GET" action="" style="display:inline-flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="tournament_id" value="{{ request('tournament_id') }}">
            <input type="hidden" name="stage" value="ラウンドロビン">
            @if($shiftValue !== '')<input type="hidden" name="shifts" value="{{ $shiftValue }}">@endif
            @if($genderValue !== '')<input type="hidden" name="gender_filter" value="{{ $genderValue }}">@endif
            <label>〇ゲーム目まで：
                <select name="upto_game">
                    @for($g = 1; $g <= max($roundRobinGames, 1); $g++)
                        <option value="{{ $g }}" {{ $g === $uptoGame ? 'selected' : '' }}>{{ $g }}</option>
                    @endfor
                </select>
            </label>
            <button class="btn btn-primary">再表示</button>
        </form>
    </div>
    @endunless

    @if(!empty($rr['missing_carry_snapshot']))
        <div class="alert alert-warning mb-0">
            ラウンドロビンの持込元 snapshot（{{ $meta['carry_snapshot_code'] ?? 'prelim_total' }}）が見つかりません。<br>
            先に直前ステージの正式成績反映を実行してください。

            @unless($isPublic)
                @if($snapshotIndexUrl)
                    <div class="mt-3">
                        <a class="btn btn-primary" href="{{ $snapshotIndexUrl }}">正式成績反映ページへ</a>
                    </div>
                @endif
            @endunless
        </div>
    @else
        <div class="rr-nav">
            <a href="#rr-matchups">対戦表</a>
            <a href="#rr-step-qualifiers">決勝ステップラダー進出者</a>
            <a href="#rr-standings">8G成績</a>
        </div>

        <div id="rr-matchups" class="rr-table-wrap">
            <h4>対戦表</h4>
            <table class="rr-table">
                <thead>
                    <tr>
                        <th>Seed</th>
                        <th class="rr-left">選手名</th>
                        @foreach($players as $col)
                            <th>{{ $col['seed'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($players as $row)
                        <tr>
                            <td>{{ $row['seed'] }}</td>
                            <td class="rr-left">{{ $row['display_name'] }}</td>
                            @foreach($players as $col)
                                @php $entries = $matrix[$row['seed']][$col['seed']] ?? []; @endphp
                                <td>
                                    @if($row['seed'] === $col['seed'])
                                        —
                                    @elseif(empty($entries))
                                        <span class="rr-mini">未対戦</span>
                                    @else
                                        @foreach($entries as $entry)
                                            @php
                                                $cls = $entry['result'] === '○' ? 'rr-win' : ($entry['result'] === '×' ? 'rr-loss' : 'rr-tie');
                                            @endphp
                                            <span class="rr-cell-line">{{ $entry['label'] }}</span>
                                            <span class="rr-cell-line">{{ $entry['text'] }}</span>
                                            <span class="rr-cell-line {{ $cls }}">{{ $entry['result'] }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="rr-note">JPBAの「対戦表」を基準にしつつ、同一マス内でゲーム番号・スコア・勝敗をまとめて確認できる形にしています。</div>
        </div>

        @if(count($stepLadderEntrants) > 0)
            <div id="rr-step-qualifiers" class="rr-step-qualifiers">
                <div class="rr-step-qualifiers-title">決勝ステップラダー進出者 {{ count($stepLadderEntrants) }}名</div>

                <div class="rr-step-qualifiers-grid">
                    @foreach($stepLadderEntrants as $index => $entrant)
                        @php
                            $photoUrl = $resolveTournamentPhotoUrl($entrant);
                            $label = $stepLadderLabels[$index] ?? (($index + 1) . '位通過');
                        @endphp

                        <div class="rr-step-entrant-card">
                            <div class="rr-step-entrant-head">{{ $label }}</div>

                            <div class="rr-step-entrant-photo">
                                @if($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="{{ $entrant['display_name'] ?? '選手写真' }}">
                                @else
                                    <span>PHOTO</span>
                                @endif
                            </div>

                            <div class="rr-step-entrant-name">
                                {{ $entrant['display_name'] ?? '—' }}
                            </div>

                            @unless($isPublic)
                                <div class="rr-step-entrant-actions">
                                    <form method="POST"
                                          action="{{ route('scores.tournament_photos.store') }}"
                                          enctype="multipart/form-data"
                                          class="rr-step-upload-form">
                                        @csrf
                                        <input type="hidden" name="tournament_id" value="{{ request('tournament_id') }}">
                                        <input type="hidden" name="participant_key" value="{{ $entrant['participant_key'] ?? '' }}">
                                        <input type="hidden" name="display_name" value="{{ $entrant['display_name'] ?? '' }}">
                                        <input type="file" name="photo" class="form-control form-control-sm" accept="image/*" required>
                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100 mt-2">大会写真を登録</button>
                                    </form>
                                </div>
                            @endunless
                        </div>
                    @endforeach
                </div>

                <div class="rr-step-photo-hint">
                    ここで登録した大会専用写真は、決勝ステップラダー画面でもそのまま共通表示します。
                </div>
            </div>
        @endif

        <div id="rr-standings" class="rr-table-wrap mt-4">
            <h4>8G成績</h4>
            <table class="rr-table">
                <thead>
                    <tr>
                        <th>RR順位</th>
                        <th>Seed</th>
                        <th class="rr-left">選手名</th>
                        <th>持込ピン</th>
                        <th>W-L-T</th>
                        <th>Bonus</th>
                        <th>RRスクラッチ</th>
                        <th>RR合計</th>
                        <th>AVG</th>
                        @for($g = 1; $g <= max($roundRobinGames, 1); $g++)
                            <th>{{ $g }}G順位</th>
                        @endfor
                        <th>通算ポイント</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($players as $player)
                        <tr>
                            <td>{{ $player['rank'] }}</td>
                            <td>{{ $player['seed'] }}</td>
                            <td class="rr-left">{{ $player['display_name'] }}</td>
                            <td>{{ number_format($player['carry_pin']) }}</td>
                            <td>{{ $player['record'] }}</td>
                            <td>{{ number_format($player['bonus_points']) }}</td>
                            <td>{{ number_format($player['rr_total_pin']) }}</td>
                            <td>{{ number_format($player['rr_total_points']) }}</td>
                            <td>{{ $player['rr_average'] !== null ? number_format((float) $player['rr_average'], 2) : '—' }}</td>
                            @for($g = 1; $g <= max($roundRobinGames, 1); $g++)
                                <td>
                                    @if(isset($player['rank_history'][$g]) && $player['rank_history'][$g] !== null)
                                        <span class="rr-chip">{{ $player['rank_history'][$g] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endfor
                            <td>{{ number_format($player['overall_total_points']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="rr-note">JPBAサンプルの「8G成績」を母体に、海外大会で見慣れた W-L-T / Bonus / RR合計 を明示しました。</div>
        </div>
    @endif
</div>
@endsection