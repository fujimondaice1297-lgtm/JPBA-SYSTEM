@extends('layouts.app')

@section('content')
<div class="container">
    <h2>年間ランキング（{{ $year }} 年度 / {{ $genderLabel ?? '全体' }}）</h2>

    {{-- 年度・性別セレクト --}}
    <form method="GET" action="{{ route('tournament_results.rankings') }}" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="year" class="form-label">年度を選択:</label>
                <select name="year" id="year" onchange="this.form.submit()" class="form-control w-auto d-inline-block">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" {{ (int) $year === (int) $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-auto">
                <label for="gender" class="form-label">性別を選択:</label>
                <select name="gender" id="gender" onchange="this.form.submit()" class="form-control w-auto d-inline-block">
                    <option value="" {{ empty($gender) ? 'selected' : '' }}>全体</option>
                    <option value="M" {{ ($gender ?? null) === 'M' ? 'selected' : '' }}>男子</option>
                    <option value="F" {{ ($gender ?? null) === 'F' ? 'selected' : '' }}>女子</option>
                </select>
            </div>
        </div>

        <div class="mt-2 text-muted small">
            現在の表示条件：{{ $year }}年度 / {{ $genderLabel ?? '全体' }}
            @if (($gender ?? null) === 'M')
                （ライセンスNoが M で始まる成績のみ）
            @elseif (($gender ?? null) === 'F')
                （ライセンスNoが F で始まる成績のみ）
            @else
                （男子・女子を含む全体）
            @endif
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
    <h4>🏆 獲得賞金ランキング（{{ $genderLabel ?? '全体' }}）</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>順位</th>
                <th>選手名</th>
                <th>合計賞金</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($moneyRanks as $rank => $entry)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $entry->name_kanji ?? '不明' }}</td>
                    <td>¥{{ number_format($entry->total_prize_money) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted">該当するランキングデータがありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ポイントランキング --}}
    <h4>🎯 獲得ポイントランキング（{{ $genderLabel ?? '全体' }}）</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>順位</th>
                <th>選手名</th>
                <th>合計ポイント</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pointRanks as $rank => $entry)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $entry->name_kanji ?? '不明' }}</td>
                    <td>{{ number_format($entry->total_points) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted">該当するランキングデータがありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- 年間アベレージランキング --}}
    <h4>📊 年間アベレージランキング（{{ $genderLabel ?? '全体' }}）</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>順位</th>
                <th>選手名</th>
                <th>アベレージ</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($averageRanks as $rank => $entry)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $entry->name_kanji ?? '不明' }}</td>
                    <td>{{ number_format($entry->avg_average, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted">該当するランキングデータがありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
