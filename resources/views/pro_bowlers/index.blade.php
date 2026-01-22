@extends('layouts.app')

@section('content')
<div class="container-fluid d-flex">

    {{-- 左サイドバー --}}
    <style>
        .sidebar a {
            white-space: nowrap;
            display: block;
            padding: 5px 10px;
        }
    </style>

    <aside class="sidebar bg-light p-3" style="width: 220px;">
        <h5>Menu</h5>
        <ul class="list-unstyled">
            <li><a href="#">ログアウト</a></li>
            <li><a href="{{ route('calendar.annual') }}">カレンダー</a></li>
            <li><a href="{{ route('informations.index') }}">INFORMATION</a></li>
            <li><a href="{{ route('informations.member') }}">会員用INFORMATION</a></li>
            <li><a href="{{ route('pro_bowlers.list') }}">全プロデータ</a></li>
            <li><a href="{{ route('pro_groups.index') }}">プロボウラーグループ管理</a></li>
            <li><a href="{{ route('hof.index') }}">日本プロボウリング殿堂</a></li>
            <li><a href="{{ route('eligibility.evergreen') }}">永久シード一覧</a></li>
            <li><a href="{{ route('eligibility.a_class.m') }}">男子A級ライセンス</a></li>
            <li><a href="{{ route('eligibility.a_class.f') }}">女子A級ライセンス</a></li>
            <li><a href="{{ route('tournaments.index') }}">大会情報</a></li>
            <li><a href="{{ route('tournament_results.index') }}">大会成績</a></li>
            <li><a href="{{ route('record_types.index') }}">公認パーフェクト等の記録</a></li>
            <li><a href="{{ route('instructors.index') }}">認定インストラクター情報</a></li>
            <li><a href="{{ route('approved_balls.index') }}">アブプールボールリスト</a></li>
            <li><a href="{{ route('registered_balls.index') }}">選手登録ボール管理</a></li>
            <li><a href="{{ route('trainings.bulk') }}">講習一括登録</a></li>
            <li><a href="{{ route('flash_news.index') }}">大会速報ページ</a></li>
            <li><a href="{{ route('scores.input') }}">大会成績速報入力管理</a></li>
            <li><a href="#">選手マイページ</a></li>
        </ul>
    </aside>

    {{-- メインコンテンツ --}}
    <main class="flex-fill px-4 py-4">

        <h3 class="fw-bold mb-4">選手データ</h3>

        {{-- 検索フォーム --}}
        <div class="mb-4">
            <div class="bg-secondary text-white px-3 py-2 fw-bold">検索条件（HPに表示）</div>
            <div class="border px-4 py-4 bg-white">
                <form method="GET" action="{{ route('pro_bowlers.index') }}" class="mb-4">
                    <div class="row g-3">
                        {{-- 1列目 --}}
                        <div class="col-md-3">
                            <input type="text" name="name" class="form-control" placeholder="例：山田 太郎" value="{{ request('name') }}">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="license_no" class="form-control" placeholder="ライセンスNo." value="{{ request('license_no') }}">
                        </div>
                        <div class="col-md-3">
                            <select name="district" class="form-select">
                                <option value="">すべて地区</option>
                                @foreach ($districts as $label)
                                    <option value="{{ $label }}" {{ request('district') == $label ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="gender" class="form-select">
                                <option value="">性別を選んでください</option>
                                <option value="男性" {{ request('gender') == '男性' ? 'selected' : '' }}>男性</option>
                                <option value="女性" {{ request('gender') == '女性' ? 'selected' : '' }}>女性</option>
                            </select>
                        </div>

                        {{-- 2列目 --}}
                        <div class="col-md-3">
                            <input type="number" name="term_from" class="form-control" placeholder="期別（開始）" value="{{ request('term_from') }}">
                        </div>
                        <div class="col-md-3">
                            <input type="number" name="term_to" class="form-control" placeholder="期別（終了）" value="{{ request('term_to') }}">
                        </div>

                        {{-- ボタン群 --}}
                        <div class="col-md-6 d-flex align-items-center gap-2">
                            <button type="submit" class="btn btn-primary">検索</button>
                            <a href="{{ route('pro_bowlers.index') }}" class="btn btn-warning">リセット</a>
                            <a href="{{ route('pro_bowlers.create') }}" class="btn btn-success">新規登録</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- 検索結果一覧 --}}
        <div class="bg-white border p-4">
        @if ($bowlers->count())
            <table class="table table-bordered">
            <thead>
                <tr>
                {{-- IDは表示しない。必要なら<tr data-id>で保持 --}}
                <th>ライセンスNo.</th>
                <th>氏名</th>
                <th>地区</th>
                <th>性別</th>
                <th>期別</th>
                <th>会員種別</th> {{-- ★ここをスポーツコーチ→会員種別に変更 --}}
                </tr>
            </thead>
            <tbody>
                @foreach ($bowlers as $bowler)
                <tr data-id="{{ $bowler->id }}">
                    <td>
                        <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                            {{ $bowler->license_no ?? '-' }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                            {{ $bowler->name_kanji ?? '-' }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                            {{ $bowler->district->label ?? '-' }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                            @if ($bowler->sex === 1) 男性
                            @elseif ($bowler->sex === 2) 女性
                            @else -
                            @endif
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                            {{ $bowler->kibetsu ? $bowler->kibetsu.'期' : '-' }}
                        </a>
                    </td>
                    <td title="{{ $bowler->membership_type ?? '' }}">
                        <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                            {{ $bowler->membership_type ?? '-' }} {{-- ★そのまま表示 --}}
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
            </table>

            <div>
            {{ $bowlers->appends(request()->query())->links() }}
            </div>
        @else
            <p>該当する選手データが見つかりませんでした。</p>
        @endif
        </div>

    </main>
</div>
@endsection
