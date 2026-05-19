@extends('layouts.app')

@section('content')
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
        <div class="d-flex gap-2">
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
            ここに登録されている選手は、同じシード年度・性別の大会でライセンスNo欄に <strong>S 0524</strong> のように表示されます。
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
                    @if ($seedList->source_url)
                        <tr>
                            <th class="table-light">参照URL</th>
                            <td colspan="3">
                                <a href="{{ $seedList->source_url }}" target="_blank" rel="noopener">
                                    {{ $seedList->source_url }}
                                </a>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">登録選手</div>
        <div class="card-body p-0">
            @if ($players->isEmpty())
                <div class="p-4 text-center text-muted">
                    登録選手はまだありません。
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">順位</th>
                                <th style="width: 150px;">ライセンスNo</th>
                                <th>氏名</th>
                                <th>フリガナ</th>
                                <th style="width: 120px;">ポイント</th>
                                <th style="width: 120px;">元順位</th>
                                <th style="width: 150px;">シード種別</th>
                                <th style="width: 90px;">状態</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($players as $player)
                                <tr>
                                    <td class="text-end">{{ $player['seed_rank'] }}</td>
                                    <td class="text-end">{{ $player['license_no'] ?: '-' }}</td>
                                    <td>{{ $player['name_kanji'] ?: '選手未特定' }}</td>
                                    <td>{{ $player['name_kana'] ?: '-' }}</td>
                                    <td class="text-end">{{ $player['point_text'] }}</td>
                                    <td class="text-end">{{ $player['ranking_rank'] ?: '-' }}</td>
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
</div>
@endsection
