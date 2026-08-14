<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="format-detection" content="telephone=no">

    <title>{{ config('app.name', 'JPBAシステム') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite(['resources/css/app.css'])

    <style>
        :root {
            --jpba-navy: #142a4a;
            --jpba-blue: #1b638d;
            --jpba-bg: #f5f7fa;
            --jpba-line: #e2e7ee;
            --jpba-muted: #667085;
        }

        body {
            min-height: 100vh;
            background: var(--jpba-bg);
            color: #1c2433;
        }

        .btn, .form-select, .form-control { min-height: 44px; }
        .btn { padding: .58rem .95rem; border-radius: .7rem; }
        .btn-sm { min-height: 36px; padding: .38rem .72rem; }

        .jpba-navbar {
            z-index: 1040;
            border-bottom: 1px solid #dce3eb;
            background: rgba(255, 255, 255, .97);
            box-shadow: 0 2px 12px rgba(18, 36, 71, .06);
            backdrop-filter: blur(10px);
        }

        .jpba-navbar .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--jpba-navy);
            font-weight: 850;
            letter-spacing: .02em;
        }

        .jpba-brand-mark {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            border-radius: 11px;
            background: linear-gradient(145deg, var(--jpba-navy), #187c86);
            color: #fff;
            font-size: .72rem;
            letter-spacing: .04em;
        }

        .jpba-navbar .nav-link {
            min-height: 42px;
            display: flex;
            align-items: center;
            padding-inline: .75rem !important;
            border-radius: .65rem;
            color: #39455a;
            font-size: .9rem;
            font-weight: 650;
        }

        .jpba-navbar .nav-link:hover,
        .jpba-navbar .nav-link.active,
        .jpba-navbar .show > .nav-link {
            background: #eef4f8;
            color: #174f79;
        }

        .jpba-navbar .dropdown-menu {
            min-width: 240px;
            padding: 8px;
            border-color: var(--jpba-line);
            border-radius: 13px;
            box-shadow: 0 14px 30px rgba(16, 24, 40, .13);
        }

        .jpba-navbar .dropdown-item {
            padding: 9px 11px;
            border-radius: 8px;
            font-size: .88rem;
        }

        .jpba-user-block {
            line-height: 1.2;
            text-align: right;
        }

        .jpba-user-block strong {
            display: block;
            font-size: .82rem;
        }

        .jpba-user-block small {
            color: var(--jpba-muted);
            font-size: .7rem;
        }

        .jpba-layout-with-menu {
            display: flex;
            align-items: flex-start;
            gap: 1.4rem;
        }

        .jpba-side-menu {
            position: sticky;
            top: 82px;
            flex: 0 0 270px;
            width: 270px;
            max-height: calc(100vh - 98px);
            overflow-y: auto;
            border: 1px solid var(--jpba-line);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 4px 16px rgba(16, 24, 40, .045);
        }

        .jpba-side-menu-header {
            padding: 16px;
            border-bottom: 1px solid var(--jpba-line);
        }

        .jpba-side-menu-title {
            margin: 0;
            color: var(--jpba-navy);
            font-size: .93rem;
            font-weight: 850;
        }

        .jpba-side-menu-subtitle {
            margin-top: 4px;
            color: var(--jpba-muted);
            font-size: .7rem;
        }

        .jpba-side-menu-body { padding: 8px; }

        .jpba-side-home,
        .jpba-side-link {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 9px 10px;
            border-radius: 9px;
            color: #344054;
            text-decoration: none;
            font-size: .82rem;
            font-weight: 650;
            line-height: 1.35;
        }

        .jpba-side-home {
            margin-bottom: 6px;
            background: #edf4f8;
            color: #174f79;
            font-weight: 800;
        }

        .jpba-side-home:hover,
        .jpba-side-link:hover,
        .jpba-side-link.active {
            background: #edf4f8;
            color: #174f79;
        }

        .jpba-side-section {
            margin-top: 5px;
            border-top: 1px solid #edf0f4;
        }

        .jpba-side-section summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px;
            cursor: pointer;
            color: #4b5565;
            font-size: .78rem;
            font-weight: 800;
            list-style: none;
        }

        .jpba-side-section summary::-webkit-details-marker { display: none; }
        .jpba-side-section summary::after { content: '+'; color: #98a2b3; font-size: 1rem; }
        .jpba-side-section[open] summary::after { content: '−'; }
        .jpba-side-section-links { padding: 0 3px 5px; }

        .jpba-side-footer {
            padding: 10px 8px 8px;
            border-top: 1px solid var(--jpba-line);
        }

        .jpba-main-content {
            flex: 1 1 auto;
            min-width: 0;
            padding-bottom: 32px;
        }

        .mobile-tabbar {
            position: sticky;
            bottom: 0;
            z-index: 1030;
            background: #fff;
            border-top: 1px solid var(--jpba-line);
            box-shadow: 0 -4px 14px rgba(16, 24, 40, .06);
        }

        .mobile-tabbar .btn {
            flex: 1;
            min-height: 52px;
            border: 0;
            border-radius: 0;
            font-size: .76rem;
        }

        @media (max-width: 991.98px) {
            .jpba-layout-with-menu {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }

            .jpba-side-menu {
                position: static;
                width: 100%;
                max-height: none;
                flex-basis: auto;
            }

            .jpba-main-content {
                width: 100%;
                max-width: 100%;
            }

            .jpba-user-block { text-align: left; padding: 9px 0; }
        }

        @media (max-width: 768px) {
            .table-responsive { -webkit-overflow-scrolling: touch; }
            th, td { white-space: nowrap; }
            h1, h2 { font-size: 1.35rem; }
            h3 { font-size: 1.15rem; }
        }
    </style>

    @laravelPWA
</head>
<body>
    @php
        $u = auth()->user();
        $isStaff = $u && ($u->isEditor() || $u->isAdmin());
        $licenseNo = $u?->proBowler?->license_no
            ?? $u?->proBowlerByLicense?->license_no
            ?? $u?->pro_bowler_license_no
            ?? '未登録';
    @endphp

    <nav class="navbar navbar-expand-xl jpba-navbar sticky-top">
        <div class="container-fluid px-3 px-xl-4">
            <a class="navbar-brand" href="{{ $isStaff ? route('management.home') : url('/') }}">
                <span class="jpba-brand-mark">JPBA</span>
                <span>JPBAシステム</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="メニューを開く">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-xl-0 ms-xl-3">
                    @auth
                        @if($isStaff)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('management.home', 'admin.home') ? 'active' : '' }}"
                                   href="{{ route('management.home') }}">管理ホーム</a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('tournaments.*', 'tournament_templates.*') ? 'active' : '' }}"
                                   href="#" role="button" data-bs-toggle="dropdown">大会運営</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('tournaments.index') }}">大会一覧・運用</a></li>
                                    <li><a class="dropdown-item" href="{{ route('tournaments.create') }}">大会を新規作成</a></li>
                                    <li><a class="dropdown-item" href="{{ route('tournament_templates.index') }}">大会テンプレート</a></li>
                                    <li><a class="dropdown-item" href="{{ route('ball_annual_registrations.index') }}">年度ボール申請承認</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('scores.*', 'tournament_results.*', 'rankings.*', 'record_types.*') ? 'active' : '' }}"
                                   href="#" role="button" data-bs-toggle="dropdown">成績・速報</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('scores.input') }}">速報・スコア入力</a></li>
                                    <li><a class="dropdown-item" href="{{ route('scores.result') }}">速報ランキング</a></li>
                                    <li><a class="dropdown-item" href="{{ route('tournament_results.index') }}">大会成績一覧</a></li>
                                    <li><a class="dropdown-item" href="{{ route('rankings.index') }}">年間ランキング</a></li>
                                    <li><a class="dropdown-item" href="{{ route('record_types.index') }}">公認記録管理</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('pro_bowlers.*', 'instructors.*', 'trainings.*') ? 'active' : '' }}"
                                   href="#" role="button" data-bs-toggle="dropdown">選手・資格</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('pro_bowlers.list') }}">全プロデータ</a></li>
                                    <li><a class="dropdown-item" href="{{ route('tournament_pro.index') }}">今年度シードプロ</a></li>
                                    <li><a class="dropdown-item" href="{{ route('instructors.index') }}">認定インストラクター</a></li>
                                    <li><a class="dropdown-item" href="{{ route('trainings.bulk') }}">講習一括登録</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('approved_balls.*', 'used_balls.*', 'registered_balls.*', 'ball_annual_registrations.*') ? 'active' : '' }}"
                                   href="#" role="button" data-bs-toggle="dropdown">ボール管理</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="{{ route('approved_balls.index') }}">ボールカタログ</a></li>
                                    <li><a class="dropdown-item" href="{{ route('used_balls.index') }}">選手登録ボール</a></li>
                                    <li><a class="dropdown-item" href="{{ route('ball_annual_registrations.index') }}">年度申請・承認</a></li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item"><a class="nav-link" href="{{ route('member.dashboard') }}">選手マイページ</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('tournament.entry.select') }}">大会エントリー</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('registered_balls.index') }}">マイボール</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('ball_annual_registrations.edit') }}">年度ボール申請</a></li>
                        @endif
                    @endauth
                </ul>

                @auth
                    <div class="d-xl-flex align-items-center gap-3">
                        <div class="jpba-user-block">
                            <strong>{{ $u?->proBowler?->name_kanji ?? $u?->name }}</strong>
                            <small>{{ $isStaff ? ($u->isAdmin() ? '管理者' : '編集者') : '選手' }}／{{ $licenseNo }}</small>
                        </div>
                        <a href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                           class="btn btn-outline-danger btn-sm">ログアウト</a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </nav>

    @if (session('status'))
        <div class="alert alert-success text-center mb-0 rounded-0">{{ session('status') }}</div>
    @endif

    @auth
        <div class="container-fluid mt-4 px-3 px-md-4">
            <div class="jpba-layout-with-menu">
                @include('partials.side_menu')
                <main class="jpba-main-content">
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <div class="container mt-4">
            @yield('content')
        </div>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @vite(['resources/js/app.js'])

    @auth
        <div class="mobile-tabbar d-flex d-md-none">
            @if($isStaff)
                <a href="{{ route('management.home') }}" class="btn btn-light">管理ホーム</a>
                <a href="{{ route('tournaments.index') }}" class="btn btn-light">大会運営</a>
                <a href="{{ route('scores.input') }}" class="btn btn-primary text-white">速報入力</a>
            @else
                <a href="{{ route('tournament.entry.select') }}" class="btn btn-light">エントリー</a>
                <a href="{{ route('registered_balls.index') }}" class="btn btn-light">マイボール</a>
                <a href="{{ route('member.dashboard') }}" class="btn btn-primary text-white">マイページ</a>
            @endif
        </div>
    @endauth

    @stack('scripts')
</body>
</html>
