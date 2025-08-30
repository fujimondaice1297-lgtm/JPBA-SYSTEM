<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">

    <title>{{ config('app.name', 'Laravel') }}</title>

    {{-- Bootstrap CSS（5.3.3） --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- 必要なら自前CSSをここで読み込む（無ければ消してOK） --}}
    @vite(['resources/css/app.css'])

    <style>
        /* 指の当たり判定UP */
        .btn, .form-select, .form-control { min-height: 44px; }
        .btn { padding: .6rem 1rem; border-radius: .75rem; }

        /* モバイルでテーブル横スクロール */
        @media (max-width: 768px) {
            .table-responsive { -webkit-overflow-scrolling: touch; }
            th, td { white-space: nowrap; }
            h1, h2 { font-size: 1.35rem; }
            h3 { font-size: 1.15rem; }
        }

        /* 画面下に固定のモバイルナビ（必要なら） */
        @media (max-width: 768px) {
            .mobile-tabbar {
            position: sticky; bottom: 0; z-index: 1030;
            background: #fff; border-top: 1px solid #eee;
            }
            .mobile-tabbar .btn { flex: 1; border-radius: 0; min-height: 48px; }
        }
    </style>

</head>
<body>
    @include('layouts.navigation')
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ url('/') }}">JPBAサイト</a>

            <div class="d-flex ms-auto">
                @auth
                    <span class="navbar-text me-3">
                        ログイン中：{{ Auth::user()->proBowler?->name_kanji ?? Auth::user()->name }}
                            （ライセンス番号: {{ Auth::user()->proBowler?->license_no ?? '未登録' }}）
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
    </nav>

    @if (session('status'))
        <div class="alert alert-success text-center mb-0 rounded-0">
            {{ session('status') }}
        </div>
    @endif

    <div class="container mt-4">
        @yield('content')
    </div>

    {{-- ページ固有スクリプト用の差込口 --}}
    @stack('scripts')

    {{-- Bootstrap JS（Popper同梱の bundle） ⇒ 必ず body の最後に置く --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- 必要なら自前JSをここで読み込む（無ければ消してOK） --}}
    @vite(['resources/js/app.js'])
</body>

    @auth
    <div class="mobile-tabbar d-flex d-md-none">
        <a href="{{ route('tournament.entry.select') }}" class="btn btn-light">エントリー</a>
        <a href="{{ route('registered_balls.index') }}" class="btn btn-light">登録ボール</a>
        <a href="{{ route('member.dashboard') }}" class="btn btn-primary text-white">マイページ</a>
    </div>
    @endauth

</html>
