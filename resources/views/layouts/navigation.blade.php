<nav class="navbar navbar-expand-md navbar-light bg-light border-bottom">
  <div class="container">
    {{-- ブランド：外部サイトへ --}}
    <a class="navbar-brand text-nowrap" href="https://www.jpba1.jp/" target="_blank" rel="noopener">
      JPBAサイト
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      {{-- 左：代表メニュー（6つ） --}}
      <ul class="navbar-nav align-items-center gap-2">
        <li class="nav-item">
          <a class="nav-link text-nowrap {{ request()->routeIs('pro_bowlers.*') ? 'active' : '' }}"
             href="{{ route('pro_bowlers.list') }}">全プロデータ</a>
        </li>

        <li class="nav-item">
          <a class="nav-link text-nowrap {{ request()->routeIs('tournaments.*') ? 'active' : '' }}"
             href="{{ route('tournaments.index') }}">大会情報</a>
        </li>

        @auth
          <li class="nav-item">
            <a class="nav-link text-nowrap {{ request()->routeIs('registered_balls.*') ? 'active' : '' }}"
               href="{{ route('registered_balls.index') }}">選手登録ボール管理</a>
          </li>

          {{-- まだ未実装なのでプレースホルダ --}}
          <li class="nav-item">
            <a class="nav-link text-nowrap {{ request()->routeIs('tournament.entry.select') ? 'active' : '' }}"
               href="{{ route('tournament.entry.select') }}">大会別使用ボール登録</a>
          </li>

          <li class="nav-item">
            <a class="nav-link text-nowrap {{ request()->routeIs('member.dashboard') ? 'active' : '' }}"
               href="{{ route('member.dashboard') }}">選手マイページ</a>
          </li>

          <li class="nav-item">
            <form method="POST" action="{{ route('logout') }}" class="m-0">
              @csrf
              <button class="btn btn-link nav-link text-nowrap" type="submit">ログアウト</button>
            </form>
          </li>
        @endauth

        @guest
          <li class="nav-item">
            <a class="nav-link text-nowrap {{ request()->routeIs('login') ? 'active' : '' }}"
               href="{{ route('login') }}">ログイン</a>
          </li>
        @endguest
      </ul>

      {{-- 右：その他（ドロップダウン） --}}
      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle text-nowrap" href="#" role="button" data-bs-toggle="dropdown">
            その他
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('calendar.annual') }}">カレンダー</a></li>
            <li><a class="dropdown-item" href="{{ route('informations.index') }}">INFORMATION</a></li>
            <li><a class="dropdown-item" href="{{ route('tournament_results.index') }}">大会成績</a></li>
            <li><a class="dropdown-item" href="{{ route('record_types.index') }}">公認パーフェクト等の記録</a></li>
            <li><a class="dropdown-item" href="{{ route('instructors.index') }}">認定インストラクター情報</a></li>
            <li><a class="dropdown-item" href="{{ route('approved_balls.index') }}">アブプールボールリスト</a></li>

            @auth
              <li><a class="dropdown-item" href="{{ route('informations.member') }}">会員用INFORMATION</a></li>
              <li><a class="dropdown-item" href="{{ route('trainings.bulk') }}">講習一括登録</a></li>
              <li><hr class="dropdown-divider"></li>
              {{-- 未実装のまま残す指定のやつ --}}
              <li><a class="dropdown-item" href="#">プログループ管理</a></li>
            @endauth
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
