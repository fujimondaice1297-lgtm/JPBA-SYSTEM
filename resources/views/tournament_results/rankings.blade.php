@extends('layouts.app')

@section('content')
<div class="container">
    <h2>年間ランキング（{{ $year }} 年度）</h2>

    {{-- 年度セレクト --}}
    <form method="GET" action="{{ route('tournament_results.rankings') }}" class="mb-4">
        <div class="form-group">
            <label for="year">年度を選択:</label>
            <select name="year" id="year" onchange="this.form.submit()" class="form-control w-auto d-inline-block">
                @foreach ($years as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="d-flex justify-content-end mb-3 gap-2">
            <a href="{{ route('tournament_results.index') }}" class="btn btn-outline-secondary">
                大会成績一覧へ戻る
            </a>
            <a href="{{ route('athlete.index') }}" class="btn btn-outline-dark">
                インデックスへ戻る
            </a>
        </div>
    </form>

    {{-- 賞金ランキング --}}
    <h4>🏆 獲得賞金ランキング</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>順位</th>
                <th>選手名</th>
                <th>合計賞金</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($moneyRanks as $rank => $entry)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $entry->name_kanji ?? '不明' }}</td>
                    <td>¥{{ number_format($entry->total_prize_money) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ポイントランキング --}}
    <h4>🎯 獲得ポイントランキング</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>順位</th>
                <th>選手名</th>
                <th>合計ポイント</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pointRanks as $rank => $entry)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $entry->name_kanji ?? '不明' }}</td>
                    <td>{{ $entry->total_points }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 年間アベレージランキング --}}
    <h4>📊 年間アベレージランキング</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>順位</th>
                <th>選手名</th>
                <th>アベレージ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($averageRanks as $rank => $entry)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $entry->name_kanji ?? '不明' }}</td>
                    <td>{{ number_format($entry->avg_average, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
