@php
    $sideUser = auth()->user();
    $sideIsStaff = $sideUser && ($sideUser->isEditor() || $sideUser->isAdmin());
    $sideGroups = $sideIsStaff
        ? app(\App\Support\ManagementNavigation::class)->groups($sideUser)
        : [];
@endphp

<aside class="jpba-side-menu" aria-label="{{ $sideIsStaff ? '管理メニュー' : '選手メニュー' }}">
    <div class="jpba-side-menu-header">
        <h2 class="jpba-side-menu-title">{{ $sideIsStaff ? '管理メニュー' : '選手メニュー' }}</h2>
        <div class="jpba-side-menu-subtitle">
            {{ $sideIsStaff ? '作業内容から画面を選択' : '登録・申請・お知らせ' }}
        </div>
    </div>

    <div class="jpba-side-menu-body">
        @if($sideIsStaff)
            <a class="jpba-side-home {{ request()->routeIs('management.home', 'admin.home') ? 'active' : '' }}"
               href="{{ route('management.home') }}">
                <span>●</span><span>管理ホーム</span>
            </a>

            @foreach($sideGroups as $group)
                @php
                    $groupActive = collect($group['items'])->contains(function (array $item): bool {
                        return collect($item['patterns'])->contains(
                            fn (string $pattern): bool => request()->routeIs($pattern)
                        );
                    });
                @endphp
                <details class="jpba-side-section" {{ $groupActive ? 'open' : '' }}>
                    <summary>
                        <span>{{ $group['label'] }}</span>
                    </summary>
                    <div class="jpba-side-section-links">
                        @foreach($group['items'] as $item)
                            @php
                                $itemActive = collect($item['patterns'])->contains(
                                    fn (string $pattern): bool => request()->routeIs($pattern)
                                );
                            @endphp
                            <a class="jpba-side-link {{ $itemActive ? 'active' : '' }}"
                               href="{{ route($item['route'], $item['route_parameters'] ?? []) }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        @else
            <a class="jpba-side-home {{ request()->routeIs('member.dashboard') ? 'active' : '' }}"
               href="{{ route('member.dashboard') }}">選手マイページ</a>
            <a class="jpba-side-link {{ request()->routeIs('tournament.entry.*') ? 'active' : '' }}"
               href="{{ route('tournament.entry.select') }}">大会エントリー</a>
            <a class="jpba-side-link {{ request()->routeIs('registered_balls.*') ? 'active' : '' }}"
               href="{{ route('registered_balls.index') }}">マイボール管理</a>
            <a class="jpba-side-link {{ request()->routeIs('ball_annual_registrations.edit') ? 'active' : '' }}"
               href="{{ route('ball_annual_registrations.edit') }}">年度ボール申請</a>
            <a class="jpba-side-link {{ request()->routeIs('informations.member*') ? 'active' : '' }}"
               href="{{ route('informations.member') }}">会員用INFORMATION</a>
            <a class="jpba-side-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}"
               href="{{ route('calendar.annual') }}">カレンダー</a>
        @endif
    </div>

    <div class="jpba-side-footer">
        <a class="jpba-side-link" href="{{ url('/') }}">一般公開サイト</a>
        <a class="jpba-side-link text-danger" href="{{ route('logout') }}"
           onclick="event.preventDefault(); document.getElementById('side-menu-logout-form').submit();">
            ログアウト
        </a>
        <form id="side-menu-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>
</aside>
