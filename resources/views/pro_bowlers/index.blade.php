@extends('layouts.app')

@section('content')
<div class="px-0 py-2">

<h3 class="fw-bold mb-4">選手データ</h3>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @php
        $importSummary = session('pro_bowler_import_summary');
    @endphp

    @if (is_array($importSummary))
        <div class="mb-4">
            <div class="border rounded bg-light p-3">
                <div class="fw-bold mb-2">直近のプロCSV取込サマリ</div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded bg-white p-3 h-100">
                            <div class="text-muted small">新規 / 更新 / スキップ</div>
                            <div class="fw-bold">
                                {{ $importSummary['created'] ?? 0 }} /
                                {{ $importSummary['updated'] ?? 0 }} /
                                {{ $importSummary['skipped'] ?? 0 }}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded bg-white p-3 h-100">
                            <div class="text-muted small">会員区分</div>
                            <div class="small">
                                player: {{ $importSummary['member_class_player'] ?? 0 }}<br>
                                pro_instructor: {{ $importSummary['member_class_pro_instructor'] ?? 0 }}<br>
                                honorary/overseas: {{ $importSummary['member_class_honorary_or_overseas'] ?? 0 }}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded bg-white p-3 h-100">
                            <div class="text-muted small">registry current</div>
                            <div class="small">
                                pro_bowler: {{ $importSummary['current_pro_bowler'] ?? 0 }}<br>
                                pro_instructor: {{ $importSummary['current_pro_instructor'] ?? 0 }}
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="border rounded bg-white p-3 h-100">
                            <div class="text-muted small">資格遷移</div>
                            <div class="small">
                                認定復帰: {{ $importSummary['reactivated_certified'] ?? 0 }}<br>
                                資格対象外: {{ $importSummary['qualification_removed'] ?? 0 }}<br>
                                昇格: {{ ($importSummary['promoted_to_pro_bowler'] ?? 0) + ($importSummary['promoted_to_pro_instructor'] ?? 0) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <a href="{{ route('pro_bowlers.list') }}" class="btn btn-outline-primary btn-sm">全プロデータへ</a>
                    <a href="{{ route('instructors.index', ['source_type' => 'pro_bowler_csv']) }}" class="btn btn-outline-dark btn-sm">インストラクター一覧（プロCSV）へ</a>
                </div>
            </div>
        </div>
    @endif

    @php
        $renewalStatusSelected = (string) request('renewal_status', 'renewed');
        $officialTournamentEligibleSelected = (string) request('official_tournament_eligible', '1');
        $playerStatusSelected = $playerStatusSelected ?? (string) request('player_status', 'active');
        $playerStatusOptions = $playerStatusOptions ?? ['active' => '現役選手', 'overseas' => '海外プロ', 'retired' => '退会者'];
        $genderSelected = in_array((string) request('gender', '男性'), ['男性', '女性'], true)
            ? (string) request('gender', '男性')
            : '男性';
    @endphp

    {{-- 検索フォーム --}}
    <div class="mb-4">
        <div class="bg-secondary text-white px-3 py-2 fw-bold">検索条件（HPに表示）</div>
        <div class="border px-4 py-4 bg-white">
            <form method="GET" action="{{ route('pro_bowlers.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="name" class="form-control" placeholder="例：山田 太郎" value="{{ request('name') }}">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="license_no" class="form-control" placeholder="ライセンスNo.（下4桁）" value="{{ request('license_no') }}">
                        <small class="text-muted">※下4桁検索は性別指定が必須です</small>
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
                        <select name="player_status" class="form-select">
                            @foreach ($playerStatusOptions as $value => $label)
                                <option value="{{ $value }}" {{ $playerStatusSelected === $value ? 'selected' : '' }}>
                                    検索区分：{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="gender" class="form-select" required>
                            <option value="男性" {{ $genderSelected === '男性' ? 'selected' : '' }}>男性</option>
                            <option value="女性" {{ $genderSelected === '女性' ? 'selected' : '' }}>女性</option>
                        </select>
                        <small class="text-muted">※ライセンスNo.重複防止のため必須</small>
                    </div>

                    <div class="col-md-3">
                        <input type="number" name="term_from" class="form-control" placeholder="期別（開始）" value="{{ request()->query('term_from', '') }}">
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="term_to" class="form-control" placeholder="期別（終了）" value="{{ request()->query('term_to', '') }}">
                    </div>

                    <div class="col-md-3">
                        <select name="renewal_status" class="form-select">
                            <option value="renewed" {{ $renewalStatusSelected === 'renewed' ? 'selected' : '' }}>更新状態：更新済</option>
                            <option value="pending" {{ $renewalStatusSelected === 'pending' ? 'selected' : '' }}>更新状態：未更新</option>
                            <option value="expired" {{ $renewalStatusSelected === 'expired' ? 'selected' : '' }}>更新状態：期限切れ</option>
                            <option value="all" {{ $renewalStatusSelected === 'all' ? 'selected' : '' }}>更新状態：すべて</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <select name="official_tournament_eligible" class="form-select">
                            <option value="1" {{ $officialTournamentEligibleSelected === '1' ? 'selected' : '' }}>公式戦：出場可</option>
                            <option value="0" {{ $officialTournamentEligibleSelected === '0' ? 'selected' : '' }}>公式戦：出場不可</option>
                            <option value="all" {{ $officialTournamentEligibleSelected === 'all' ? 'selected' : '' }}>公式戦：すべて</option>
                        </select>
                    </div>

                    <div class="col-md-6 d-flex align-items-center gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">検索</button>
                        <a href="{{ route('pro_bowlers.index') }}" class="btn btn-warning">リセット</a>
                        <a href="{{ route('pro_bowlers.create') }}" class="btn btn-success">新規登録</a>
                        <a href="{{ route('pro_bowlers.import_form') }}" class="btn btn-dark">プロCSV取込</a>
                        <a href="{{ route('instructors.import_auth_form') }}" class="btn btn-outline-dark">認定CSV取込</a>
                        <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 検索結果一覧 --}}
    <div class="bg-white border p-4">
    @if ($bowlers->count())
        <table class="table table-bordered align-middle">
        <thead>
            <tr>
            <th>ライセンスNo.</th>
            <th>氏名</th>
            <th>地区</th>
            <th>性別</th>
            <th>期別</th>
            <th>会員区分</th>
            <th>公式戦</th>
            <th>保有インストラクター資格</th>
            <th>更新状態</th>
            <th>スポーツコーチ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bowlers as $bowler)
            @php
                $heldInstructorQualificationLabel = '保有なし';
                if (($bowler->a_class_status ?? null) === '有') {
                    $heldInstructorQualificationLabel = 'A級';
                } elseif (($bowler->b_class_status ?? null) === '有') {
                    $heldInstructorQualificationLabel = 'B級';
                } elseif (($bowler->c_class_status ?? null) === '有') {
                    $heldInstructorQualificationLabel = 'C級';
                }

                $displayLicenseNo = '-';
                if (($bowler->license_no_num ?? null) !== null && $bowler->license_no_num !== '') {
                    $displayLicenseNo = str_pad((string) ((int) $bowler->license_no_num), 4, '0', STR_PAD_LEFT);
                } elseif (preg_match('/(\d{1,4})$/', (string) ($bowler->license_no ?? ''), $matches)) {
                    $displayLicenseNo = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
                }
            @endphp
            <tr data-id="{{ $bowler->id }}">
                <td>
                <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                    {{ $displayLicenseNo }}
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
                    {{ $bowler->gender }}
                </a>
                </td>
                <td>
                <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                    {{ $bowler->kibetsu ? $bowler->kibetsu.'期' : '-' }}
                </a>
                </td>
                <td>
                <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                    {{ $bowler->member_class_label }}
                </a>
                </td>
                <td>
                <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                    {{ $bowler->official_tournament_eligibility_label }}
                </a>
                </td>
                <td>
                <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                    {{ $heldInstructorQualificationLabel }}
                </a>
                </td>
                <td>
                <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                    {{ $bowler->current_instructor_renewal_status_label }}
                </a>
                </td>
                <td>
                <a href="{{ route('pro_bowlers.edit', $bowler->id) }}" class="text-decoration-none text-dark">
                    {{ $bowler->sports_coach_label }}
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

</div>
@endsection
