@extends('layouts.app')

@section('content')
<style>
    .management-dashboard {
        --ink: #172033;
        --muted: #667085;
        --line: #e4e8ef;
        --panel: #ffffff;
        max-width: 1420px;
        margin: 0 auto;
        color: var(--ink);
    }

    .management-hero {
        position: relative;
        overflow: hidden;
        padding: 28px 30px;
        border-radius: 20px;
        background: linear-gradient(130deg, #122447 0%, #1c4b77 58%, #15787f 100%);
        color: #fff;
        box-shadow: 0 14px 34px rgba(18, 36, 71, .18);
    }

    .management-hero::after {
        content: '';
        position: absolute;
        right: -55px;
        top: -95px;
        width: 270px;
        height: 270px;
        border: 44px solid rgba(255, 255, 255, .08);
        border-radius: 50%;
    }

    .management-hero-label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 5px 10px;
        border: 1px solid rgba(255, 255, 255, .25);
        border-radius: 999px;
        background: rgba(255, 255, 255, .1);
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .08em;
    }

    .management-hero h1 {
        margin: 12px 0 6px;
        font-size: clamp(1.7rem, 3vw, 2.35rem);
        font-weight: 800;
        letter-spacing: .02em;
    }

    .management-hero p {
        max-width: 760px;
        margin: 0;
        color: rgba(255, 255, 255, .82);
    }

    .management-section {
        margin-top: 28px;
    }

    .management-section-heading {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 14px;
    }

    .management-section-heading h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
    }

    .management-section-heading p {
        margin: 4px 0 0;
        color: var(--muted);
        font-size: .9rem;
    }

    .management-quick-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
    }

    .management-quick-action {
        --action-color: #1b638d;
        --action-soft: #e8f2f7;
        display: flex;
        flex-direction: column;
        min-height: 120px;
        padding: 17px;
        border: 1px solid var(--line);
        border-top: 5px solid var(--action-color);
        border-radius: 15px;
        background: var(--panel);
        color: var(--ink);
        text-decoration: none;
        box-shadow: 0 3px 12px rgba(16, 24, 40, .04);
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }

    .management-quick-action:hover {
        transform: translateY(-2px);
        border-color: var(--action-color);
        color: var(--action-color);
        box-shadow: 0 10px 24px rgba(25, 70, 110, .1);
    }

    .quick-tone-blue { --action-color: #2767a8; --action-soft: #e7f0ff; }
    .quick-tone-orange { --action-color: #a85b10; --action-soft: #fff0dd; }
    .quick-tone-navy { --action-color: #3a557c; --action-soft: #e8edf5; }
    .quick-tone-teal { --action-color: #167363; --action-soft: #e3f6f2; }
    .quick-tone-rose { --action-color: #a83d59; --action-soft: #fdebef; }

    .management-quick-action strong {
        font-size: 1rem;
    }

    .management-quick-action span {
        margin-top: 6px;
        color: var(--muted);
        font-size: .82rem;
    }

    .management-quick-action b {
        display: inline-flex;
        align-items: center;
        align-self: flex-start;
        margin-top: auto;
        padding: 6px 10px;
        border-radius: 8px;
        background: var(--action-color);
        color: #fff;
        font-size: .8rem;
    }

    .management-metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .management-metric {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 17px 18px;
        border: 1px solid var(--line);
        border-radius: 15px;
        background: #fff;
        color: var(--ink);
        text-decoration: none;
    }

    .management-metric:hover {
        border-color: #9bb3c8;
        color: var(--ink);
    }

    .management-metric-label {
        color: var(--muted);
        font-size: .82rem;
        font-weight: 700;
    }

    .management-metric-note {
        margin-top: 3px;
        color: #98a2b3;
        font-size: .74rem;
    }

    .management-metric-value {
        flex: 0 0 auto;
        font-size: 1.9rem;
        font-weight: 850;
        color: #194e78;
    }

    .management-workflow {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0;
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #fff;
    }

    .management-workflow-step {
        position: relative;
        min-height: 170px;
        padding: 20px;
        border-right: 1px solid var(--line);
    }

    .management-workflow-step:last-child {
        border-right: 0;
    }

    .management-workflow-number {
        display: inline-grid;
        place-items: center;
        width: 30px;
        height: 30px;
        margin-bottom: 12px;
        border-radius: 9px;
        background: #e8f2f7;
        color: #155b78;
        font-weight: 800;
    }

    .management-workflow-step h3 {
        margin: 0 0 6px;
        font-size: 1rem;
        font-weight: 800;
    }

    .management-workflow-step p {
        min-height: 40px;
        margin: 0 0 14px;
        color: var(--muted);
        font-size: .8rem;
    }

    .management-workflow-step a {
        display: inline-flex;
        align-items: center;
        padding: 7px 10px;
        border-radius: 8px;
        background: #2767a8;
        color: #fff;
        font-size: .82rem;
        font-weight: 750;
        text-decoration: none;
    }

    .management-workflow-step:nth-child(2) a { background: #167363; }
    .management-workflow-step:nth-child(3) a { background: #a85b10; }
    .management-workflow-step:nth-child(4) a { background: #6743a5; }

    .management-workflow-step a:hover {
        color: #fff;
        filter: brightness(.92);
    }

    .management-tournament-list {
        display: grid;
        gap: 10px;
    }

    .management-tournament-row {
        display: grid;
        grid-template-columns: minmax(230px, 1.5fr) minmax(170px, .8fr) auto;
        align-items: center;
        gap: 18px;
        padding: 16px 18px;
        border: 1px solid var(--line);
        border-radius: 15px;
        background: #fff;
    }

    .management-tournament-row h3 {
        margin: 0 0 5px;
        font-size: .98rem;
        font-weight: 800;
    }

    .management-tournament-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 12px;
        color: var(--muted);
        font-size: .78rem;
    }

    .management-tournament-status {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .management-tournament-count {
        color: var(--muted);
        font-size: .8rem;
    }

    .management-tournament-count strong {
        color: var(--ink);
        font-size: 1.05rem;
    }

    .management-tournament-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 7px;
    }

    .management-group-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    .management-group-card {
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 17px;
        background: #fff;
        box-shadow: 0 3px 12px rgba(16, 24, 40, .035);
    }

    .management-group-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px;
        border-bottom: 1px solid var(--line);
        background: #f9fafb;
    }

    .management-group-mark {
        display: grid;
        place-items: center;
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        border-radius: 13px;
        font-size: .76rem;
        font-weight: 850;
        letter-spacing: .03em;
    }

    .tone-blue .management-group-mark { background: #e7f0ff; color: #245fa8; }
    .tone-teal .management-group-mark { background: #e3f6f2; color: #147363; }
    .tone-orange .management-group-mark { background: #fff0dd; color: #a8580c; }
    .tone-purple .management-group-mark { background: #f0eaff; color: #6743a5; }
    .tone-navy .management-group-mark { background: #e8edf5; color: #334d72; }
    .tone-rose .management-group-mark { background: #fdebef; color: #a83d59; }

    .management-group-header h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .management-group-header p {
        margin: 3px 0 0;
        color: var(--muted);
        font-size: .76rem;
        line-height: 1.45;
    }

    .management-group-links {
        padding: 7px;
    }

    .management-group-link {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 11px;
        border-radius: 10px;
        color: var(--ink);
        text-decoration: none;
    }

    .management-group-link:hover {
        background: #f2f6f9;
        color: #174f79;
    }

    .management-group-link strong {
        display: block;
        font-size: .85rem;
    }

    .management-group-link span {
        display: block;
        margin-top: 2px;
        color: var(--muted);
        font-size: .72rem;
        line-height: 1.4;
    }

    .management-group-link::after {
        content: '›';
        display: grid;
        place-items: center;
        align-self: center;
        width: 30px;
        height: 30px;
        flex: 0 0 30px;
        border-radius: 9px;
        background: #667085;
        color: #fff;
        font-size: 1.25rem;
    }

    .tone-blue .management-group-link::after { background: #2767a8; }
    .tone-teal .management-group-link::after { background: #167363; }
    .tone-orange .management-group-link::after { background: #a85b10; }
    .tone-purple .management-group-link::after { background: #6743a5; }
    .tone-navy .management-group-link::after { background: #3a557c; }
    .tone-rose .management-group-link::after { background: #a83d59; }

    @media (max-width: 1200px) {
        .management-quick-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .management-group-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 900px) {
        .management-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .management-workflow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .management-workflow-step:nth-child(2) { border-right: 0; }
        .management-workflow-step:nth-child(-n+2) { border-bottom: 1px solid var(--line); }
        .management-tournament-row { grid-template-columns: 1fr; }
        .management-tournament-actions { justify-content: flex-start; }
    }

    @media (max-width: 620px) {
        .management-hero { padding: 22px 20px; border-radius: 15px; }
        .management-quick-grid,
        .management-metric-grid,
        .management-group-grid { grid-template-columns: 1fr; }
        .management-workflow { grid-template-columns: 1fr; }
        .management-workflow-step,
        .management-workflow-step:nth-child(2) { border-right: 0; border-bottom: 1px solid var(--line); }
        .management-workflow-step:last-child { border-bottom: 0; }
        .management-section-heading { align-items: flex-start; flex-direction: column; }
    }
</style>

<div class="management-dashboard">
    <section class="management-hero">
        <div class="management-hero-label">JPBA MANAGEMENT</div>
        <h1>管理者ワークスペース</h1>
        <p>
            {{ auth()->user()?->name }}さん、{{ now()->format('Y年n月j日') }}の作業画面です。
            大会の準備から公開まで、作業の段階に沿って進められます。
        </p>
    </section>

    <section class="management-section">
        <div class="management-section-heading">
            <div>
                <h2>よく使う操作</h2>
                <p>日常的な作業へワンクリックで移動します。</p>
            </div>
        </div>
        <div class="management-quick-grid">
            @php
                $quickTones = ['blue', 'orange', 'navy', 'teal', 'rose'];
            @endphp
            @foreach($quickActions as $action)
                <a class="management-quick-action quick-tone-{{ $quickTones[$loop->index % count($quickTones)] }}"
                   href="{{ route($action['route']) }}">
                    <strong>{{ $action['label'] }}</strong>
                    <span>{{ $action['description'] }}</span>
                    <b>画面を開く →</b>
                </a>
            @endforeach
        </div>
    </section>

    <section class="management-section">
        <div class="management-section-heading">
            <div>
                <h2>対応状況</h2>
                <p>現在の作業量を確認し、対象一覧へ移動できます。</p>
            </div>
        </div>
        <div class="management-metric-grid">
            @foreach($metrics as $metric)
                <a class="management-metric" href="{{ route($metric['route']) }}">
                    <div>
                        <div class="management-metric-label">{{ $metric['label'] }}</div>
                        <div class="management-metric-note">{{ $metric['note'] }}</div>
                    </div>
                    <div class="management-metric-value">{{ number_format($metric['value']) }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="management-section">
        <div class="management-section-heading">
            <div>
                <h2>大会運営の流れ</h2>
                <p>迷ったときは左から順に進めます。</p>
            </div>
        </div>
        <div class="management-workflow">
            <article class="management-workflow-step">
                <div class="management-workflow-number">1</div>
                <h3>大会を準備</h3>
                <p>大会要項、会場、申込期間、ボール上限、成績形式を設定します。</p>
                <a href="{{ route('tournaments.index') }}">大会一覧・編集へ →</a>
            </article>
            <article class="management-workflow-step">
                <div class="management-workflow-number">2</div>
                <h3>参加受付</h3>
                <p>参加選手、シード、抽選、登録ボールと検量証を確認します。</p>
                <a href="{{ route('tournaments.index') }}">対象大会を選ぶ →</a>
            </article>
            <article class="management-workflow-step">
                <div class="management-workflow-number">3</div>
                <h3>大会当日</h3>
                <p>チェックイン、レーン、スコア入力、速報ランキングを運用します。</p>
                <a href="{{ route('scores.input') }}">速報入力へ →</a>
            </article>
            <article class="management-workflow-step">
                <div class="management-workflow-number">4</div>
                <h3>確定・公開</h3>
                <p>最終成績、ポイント、賞金、公認記録を確認して公開します。</p>
                <a href="{{ route('tournament_results.index') }}">大会成績へ →</a>
            </article>
        </div>
    </section>

    <section class="management-section">
        <div class="management-section-heading">
            <div>
                <h2>大会ごとの作業</h2>
                <p>開催予定および直近の大会から、必要な画面へ直接移動します。</p>
            </div>
            <a class="btn btn-primary btn-sm" href="{{ route('tournaments.index') }}">すべての大会を見る</a>
        </div>
        <div class="management-tournament-list">
            @forelse($tournaments as $tournament)
                @php
                    $hasEnded = $tournament->end_date && $tournament->end_date->isBefore(today());
                    $isToday = $tournament->start_date && $tournament->start_date->isToday();
                @endphp
                <article class="management-tournament-row">
                    <div>
                        <h3>{{ $tournament->name }}</h3>
                        <div class="management-tournament-meta">
                            <span>{{ optional($tournament->start_date)->format('Y年n月j日') ?: '開催日未設定' }}</span>
                            <span>{{ $tournament->venue_name ?: '会場未設定' }}</span>
                        </div>
                    </div>
                    <div class="management-tournament-status">
                        <span class="badge {{ $hasEnded ? 'text-bg-secondary' : ($isToday ? 'text-bg-danger' : 'text-bg-primary') }}">
                            {{ $hasEnded ? '開催済み' : ($isToday ? '本日開催' : '開催予定') }}
                        </span>
                        <span class="management-tournament-count">
                            参加 <strong>{{ number_format($tournament->confirmed_entries_count) }}</strong>名
                        </span>
                    </div>
                    <div class="management-tournament-actions">
                        <a class="btn btn-secondary btn-sm" href="{{ route('tournaments.edit', $tournament) }}">設定</a>
                        <a class="btn btn-dark btn-sm" href="{{ route('tournaments.entries.index', $tournament) }}">参加者</a>
                        <a class="btn btn-warning btn-sm" href="{{ route('scores.input', ['tournament_id' => $tournament->id]) }}">速報</a>
                        <a class="btn btn-success btn-sm" href="{{ route('tournaments.results.index', $tournament) }}">成績</a>
                        <a class="btn btn-primary btn-sm" href="{{ route('tournaments.result_publications.index', $tournament) }}">公開</a>
                    </div>
                </article>
            @empty
                <div class="alert alert-light border mb-0">表示できる大会がありません。</div>
            @endforelse
        </div>
    </section>

    <section class="management-section mb-5">
        <div class="management-section-heading">
            <div>
                <h2>すべての作業メニュー</h2>
                <p>作業内容から目的の画面を選べます。</p>
            </div>
        </div>
        <div class="management-group-grid">
            @foreach($managementGroups as $group)
                <article class="management-group-card tone-{{ $group['tone'] }}">
                    <header class="management-group-header">
                        <div class="management-group-mark">{{ $group['short_label'] }}</div>
                        <div>
                            <h3>{{ $group['label'] }}</h3>
                            <p>{{ $group['description'] }}</p>
                        </div>
                    </header>
                    <div class="management-group-links">
                        @foreach($group['items'] as $item)
                            <a class="management-group-link" href="{{ route($item['route'], $item['route_parameters'] ?? []) }}">
                                <span>
                                    <strong>{{ $item['label'] }}</strong>
                                    <span>{{ $item['description'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
@endsection
