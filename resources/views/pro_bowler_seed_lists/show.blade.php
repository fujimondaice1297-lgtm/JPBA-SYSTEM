@extends('layouts.app')

@section('content')
@php
    $sectionLabels = $seedList->gender === 'M'
        ? [
            'top_24_seed' => '上位24名',
            'permanent_seed' => '永久シード',
            'semi_permanent_seed' => '準永久シード',
        ]
        : [
            'first_seed' => '第1シード',
            'second_seed' => '第2シード',
            'permanent_seed' => '永久シード',
            'semi_permanent_seed' => '準永久シード',
        ];

    $sectionedPlayers = collect($players)->groupBy(function ($player) use ($seedList) {
        $category = strtoupper((string) ($player['seed_category'] ?? ''));
        $seedRank = (int) ($player['seed_rank'] ?? 0);

        if ($category === 'V20') {
            return 'permanent_seed';
        }

        if ($category === 'V10') {
            return 'semi_permanent_seed';
        }

        if ($seedList->gender === 'M') {
            return 'top_24_seed';
        }

        return $seedRank >= 19 ? 'second_seed' : 'first_seed';
    });
@endphp

<div class="container" style="max-width: 1120px;">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h2 class="mb-1">年度別シード詳細</h2>
            <div class="text-muted">
                {{ $seedList->seed_year }}年
                {{ $genderLabels[$seedList->gender] ?? $seedList->gender }}
                シード / 元ランキング {{ $seedList->base_ranking_year }}年
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <div class="btn-group" role="group" aria-label="性別切り替え">
                @foreach (['M' => '男子', 'F' => '女子'] as $genderCode => $genderButtonLabel)
                    @php
                        $targetSeedList = $genderSwitchSeedLists->get($genderCode);
                        $isCurrentGender = $seedList->gender === $genderCode;
                    @endphp

                    @if ($targetSeedList)
                        <a href="{{ route('pro_bowler_seed_lists.show', $targetSeedList) }}"
                           class="btn {{ $isCurrentGender ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $genderButtonLabel }}
                        </a>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>
                            {{ $genderButtonLabel }} 未作成
                        </button>
                    @endif
                @endforeach
            </div>

            <a href="{{ route('pro_bowler_seed_lists.index') }}" class="btn btn-outline-secondary">
                年度別シード一覧へ
            </a>
            <a href="{{ route('tournament_results.rankings', ['year' => $seedList->base_ranking_year]) }}"
               class="btn btn-outline-primary">
                ポイントランキングへ
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        <div class="fw-bold mb-1">この詳細画面の役割</div>
        <div>
            前年度ポイントランキングや例外入力から作成された年度別シード対象者を確認します。<br>
            ここに登録されている選手は、同じシード年度・性別の大会でライセンスNo欄に <strong>S 0524</strong> のように表示されます。<br>
            男子は上位24名、女子は1〜18位を第1シード、19〜36位を第2シードとして表示します。
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">シード一覧情報</div>
        <div class="card-body p-0">
            <table class="table table-bordered align-middle mb-0">
                <tbody>
                    <tr>
                        <th class="table-light" style="width: 180px;">シード年度</th>
                        <td>{{ $seedList->seed_year }}</td>
                        <th class="table-light" style="width: 180px;">性別</th>
                        <td>{{ $genderLabels[$seedList->gender] ?? $seedList->gender }}</td>
                    </tr>
                    <tr>
                        <th class="table-light">元ランキング年度</th>
                        <td>{{ $seedList->base_ranking_year }}</td>
                        <th class="table-light">上位人数</th>
                        <td>{{ $seedList->base_top_count }}</td>
                    </tr>
                    <tr>
                        <th class="table-light">登録人数</th>
                        <td>{{ $players->count() }}</td>
                        <th class="table-light">有効状態</th>
                        <td>{{ $seedList->is_active ? '有効' : '無効' }}</td>
                    </tr>
                    <tr>
                        <th class="table-light">備考</th>
                        <td colspan="3">{{ $seedList->notes ?: '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($players->isEmpty())
        <div class="card">
            <div class="card-header fw-bold">登録選手</div>
            <div class="card-body">
                <div class="p-4 text-center text-muted">
                    登録選手はまだありません。
                </div>
            </div>
        </div>
    @else
        @foreach ($sectionLabels as $sectionKey => $sectionLabel)
            @php
                $sectionPlayers = $sectionedPlayers->get($sectionKey, collect())->sortBy('seed_rank')->values();
            @endphp

            <div class="card mb-4">
                <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                    <span>{{ $sectionLabel }}</span>
                    <span class="badge bg-secondary">{{ $sectionPlayers->count() }}名</span>
                </div>
                <div class="card-body p-0">
                    @if ($sectionPlayers->isEmpty())
                        <div class="p-4 text-center text-muted">
                            {{ $sectionLabel }}の登録はまだありません。
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">順位</th>
                                        <th style="width: 110px;">ライセンスNo</th>
                                        <th>氏名</th>
                                        <th>フリガナ</th>
                                        <th style="width: 80px;">期</th>
                                        <th style="width: 120px;">ポイント</th>
                                        <th style="width: 140px;">獲得賞金</th>
                                        <th style="width: 150px;">シード種別</th>
                                        <th style="width: 90px;">状態</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sectionPlayers as $player)
                                        <tr>
                                            <td class="text-end">{{ $player['seed_rank'] }}</td>
                                            <td class="text-end">
                                                @if ($player['license_no'])
                                                    <span title="{{ $player['license_no'] }}">{{ $player['display_license_no'] }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $player['name_kanji'] ?: '選手未特定' }}</td>
                                            <td>{{ $player['name_kana'] ?: '-' }}</td>
                                            <td class="text-end">{{ $player['kibetsu'] }}</td>
                                            <td class="text-end">{{ $player['points'] }}</td>
                                            <td class="text-end">{{ $player['prize_money'] }}</td>
                                            <td>{{ $seedCategoryLabels[$player['seed_category']] ?? $player['seed_category'] }}</td>
                                            <td>{{ $player['is_active'] ? '有効' : '無効' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
