<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="format-detection" content="telephone=no">

    <title>{{ config('app.name', 'Laravel') }}</title>

    {{-- Bootstrap CSS（5.3.3） --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- 必要なら自前CSS --}}
    @vite(['resources/css/app.css'])

    <style>
        .btn, .form-select, .form-control { min-height: 44px; }
        .btn { padding: .6rem 1rem; border-radius: .75rem; }

        @media (max-width: 768px) {
            .table-responsive { -webkit-overflow-scrolling: touch; }
            th, td { white-space: nowrap; }
            h1, h2 { font-size: 1.35rem; }
            h3 { font-size: 1.15rem; }
        }

        @media (max-width: 768px) {
            .mobile-tabbar {
                position: sticky; bottom: 0; z-index: 1030;
                background: #fff; border-top: 1px solid #eee;
            }
            .mobile-tabbar .btn { flex: 1; border-radius: 0; min-height: 48px; }
        }
    </style>
    {{-- PWA: manifest, meta, service worker --}}
    @laravelPWA
</head>
<body>
    @php $u = auth()->user(); @endphp

    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ url('/') }}">JPBAサイト</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="切替">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @auth
                        <li class="nav-item"><a class="nav-link" href="{{ route('member.dashboard') }}">選手マイページ</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('tournament.entry.select') }}">大会エントリー</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('registered_balls.index') }}">ボール登録</a></li>

                        @if($u?->isEditor() || $u?->isAdmin())
                            <li class="nav-item"><a class="nav-link" href="{{ route('pro_bowlers.index') }}">全プロデータ</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('tournaments.index') }}">大会管理</a></li>
                            <li class="nav-item"><a class="nav-link" href="{{ route('approved_balls.index') }}">承認ボール管理</a></li>
                        @endif

                        @if($u?->isAdmin())
                            <li class="nav-item"><a class="nav-link text-danger" href="{{ route('admin.home') }}">管理</a></li>
                        @endif
                    @endauth
                </ul>

                <div class="d-flex ms-auto align-items-center">
                    @auth
                        <span class="navbar-text me-3">
                            ログイン中：{{ $u?->proBowler?->name_kanji ?? $u?->name }}
                            （ライセンス番号:
                                {{ $u?->proBowler?->license_no
                                    ?? $u?->proBowlerByLicense?->license_no
                                    ?? $u?->pro_bowler_license_no
                                    ?? '未登録' }}）
                        </span>
                        <a href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                           class="btn btn-outline-danger btn-sm">
                            ログアウト
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    @if (session('status'))
        <div class="alert alert-success text-center mb-0 rounded-0">
            {{ session('status') }}
        </div>
    @endif

    <div class="container mt-4">
        @yield('content')
    </div>

    {{-- Bootstrap JS（Popper同梱） --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- 自前JS --}}
    @vite(['resources/js/app.js'])

    @auth
    <div class="mobile-tabbar d-flex d-md-none">
        <a href="{{ route('tournament.entry.select') }}" class="btn btn-light">エントリー</a>
        <a href="{{ route('registered_balls.index') }}" class="btn btn-light">ボール登録</a>
        <a href="{{ route('member.dashboard') }}" class="btn btn-primary text-white">マイページ</a>
    </div>
    @endauth

    {{-- ★ ページ固有スクリプトは最後に1回だけ --}}
    @stack('scripts')
</body>
</html>
