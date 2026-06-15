@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1">今年度シードプロ</h3>
            <div class="text-muted">
                年度別シード一覧を正本として、男子上位24名、女子第1シード・第2シード、永久シード、準永久シードを確認します。
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('pro_bowler_seed_lists.index') }}" class="btn btn-outline-primary">
                年度別シード管理へ
            </a>
            <a href="{{ route('pro_bowlers.list') }}" class="btn btn-outline-secondary">
                全プロデータへ
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        <div class="fw-bold mb-1">この画面の見方</div>
        <div>
            大会PDFなどでライセンスNoの前に <strong>S</strong> を付ける判定と同じく、年度別シード一覧を基準に表示します。<br>
            男子はランキング由来の上位24名、女子はランキング由来の1〜18位を第1シード、19〜36位を第2シードとして表示します。<br>
            永久シード・準永久シードはランキング由来シードとは別枠で、この後の登録工程で追加します。
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-bold">表示条件</div>
        <div class="card-body">
            <form method="GET" action="{{ route('tournament_pro.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">年度</label>
                    <select name="year" class="form-select">
                        @foreach ($availableYears as $year)
                            <option value="{{ $year }}" {{ (int) $selectedYear === (int) $year ? 'selected' : '' }}>
                                {{ $year }}年
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">性別</label>
                    <select name="gender" class="form-select">
                        <option value="" {{ $selectedGender === null ? 'selected' : '' }}>男女すべて</option>
                        @foreach ($genderLabels as $genderCode => $genderLabel)
                            <option value="{{ $genderCode }}" {{ $selectedGender === $genderCode ? 'selected' : '' }}>
                                {{ $genderLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">表示</button>
                    <a href="{{ route('tournament_pro.index') }}" class="btn btn-warning">リセット</a>
                </div>
            </form>
        </div>
    </div>

    @php
        $visibleGenders = $selectedGender ? [$selectedGender] : array_keys($genderLabels);
        $hasAnyRows = false;
    @endphp

    @foreach ($visibleGenders as $genderCode)
        @php
            $sectionLabels = $sectionLabelsByGender[$genderCode] ?? [];
        @endphp

        <div class="mb-5">
            <h4 class="fw-bold border-bottom pb-2 mb-3">
                {{ $selectedYear }}年 {{ $genderLabels[$genderCode] ?? $genderCode }} シードプロ
            </h4>

            @if ($genderCode === 'M')
                <div class="text-muted mb-3">男子は第1・第2には分けず、前年度最終ランキング上位24名を表示します。</div>
            @elseif ($genderCode === 'F')
                <div class="text-muted mb-3">女子は前年度最終ランキング1〜18位を第1シード、19〜36位を第2シードとして表示します。</div>
            @endif

            @foreach ($sectionLabels as $sectionKey => $sectionLabel)
                @php
                    $rows = $sections[$genderCode][$sectionKey] ?? collect();
                    if ($rows->isNotEmpty()) {
                        $hasAnyRows = true;
                    }
                @endphp

                <div class="card mb-4">
                    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                        <span>{{ $sectionLabel }}</span>
                        <span class="badge bg-secondary">{{ $rows->count() }}名</span>
                    </div>
                    <div class="card-body p-0">
                        @if ($rows->isEmpty())
                            <div class="p-4 text-center text-muted">
                                {{ $sectionLabel }}の登録はまだありません。
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 80px;">順位</th>
                                            <th style="width: 120px;">ライセンスNo</th>
                                            <th style="width: 180px;">氏名</th>
                                            <th style="width: 220px;">フリガナ</th>
                                            <th style="width: 90px;">期</th>
                                            <th style="width: 130px;">ポイント</th>
                                            <th style="width: 140px;">獲得賞金</th>
                                            <th style="width: 130px;">地区</th>
                                            <th style="width: 160px;">シード種別</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr>
                                                <td class="text-end">{{ $row['seed_rank'] ?: '-' }}</td>
                                                <td class="text-end">
                                                    @if ($row['license_no'])
                                                        <span title="{{ $row['license_no'] }}">{{ $row['display_license_no'] }}</span>
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ $row['name_kanji'] }}</td>
                                                <td>{{ $row['name_kana'] }}</td>
                                                <td class="text-end">{{ $row['kibetsu'] }}</td>
                                                <td class="text-end">{{ $row['points'] }}</td>
                                                <td class="text-end">{{ $row['prize_money'] }}</td>
                                                <td>{{ $row['district'] }}</td>
                                                <td>{{ $row['seed_category_label'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    @if (! $hasAnyRows)
        <div class="alert alert-warning">
            <div class="fw-bold mb-1">{{ $selectedYear }}年のシードプロはまだ登録されていません。</div>
            <div>
                まず「年度別シード管理」から男子・女子のシード一覧を作成してください。登録後、この画面に男子上位24名、女子第1シード・第2シード、永久シード、準永久シードとして表示されます。
            </div>
        </div>
    @endif
</div>
@endsection
