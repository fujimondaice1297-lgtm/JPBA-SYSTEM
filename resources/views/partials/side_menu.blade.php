<aside class="jpba-side-menu bg-light p-3">
    <h5>Menu</h5>

    <ul class="list-unstyled mb-0">
        <li>
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('side-menu-logout-form').submit();">
                ログアウト
            </a>
            <form id="side-menu-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </li>
        <li><a href="{{ route('calendar.annual') }}">カレンダー</a></li>
        <li><a href="{{ route('informations.index') }}">INFORMATION</a></li>
        <li><a href="{{ route('informations.member') }}">会員用INFORMATION</a></li>
        <li><a href="{{ route('pro_bowlers.list') }}">全プロデータ</a></li>
        <li><a href="{{ route('tournament_pro.index') }}">今年度シードプロ</a></li>
        <li><a href="{{ route('tp_registration.index') }}">TP登録会受講情報</a></li>
        <li><a href="{{ route('tournaments.index') }}">大会情報</a></li>
        <li><a href="{{ route('tournament_results.index') }}">大会成績</a></li>
        <li><a href="{{ route('record_types.index') }}">公認パーフェクト等の記録</a></li>
        <li><a href="{{ route('pro_groups.index') }}">プログループ管理</a></li>
        <li><a href="{{ route('instructors.index') }}">認定インストラクター情報</a></li>
        <li><a href="{{ route('approved_balls.index') }}">アブプールボールリスト</a></li>
        <li><a href="{{ route('registered_balls.index') }}">選手登録ボール管理</a></li>
        <li><a href="{{ route('trainings.bulk') }}">講習一括登録</a></li>
        <li><a href="{{ route('used_balls.index') }}">大会別使用ボール登録</a></li>
        <li><a href="{{ url('/') }}">トップ</a></li>
        <li><a href="{{ route('member.dashboard') }}">選手マイページ</a></li>
    </ul>
</aside>
