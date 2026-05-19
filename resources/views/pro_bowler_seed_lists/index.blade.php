@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1120px;">
    <style>
        .seed-form-grid {
            display: grid;
            grid-template-columns: 140px 150px 240px 140px minmax(220px, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .seed-manual-grid {
            display: grid;
            grid-template-columns: 140px 150px 180px 140px minmax(260px, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .seed-field .form-label {
            display: block;
            min-height: 1.5rem;
            margin-bottom: .35rem;
            white-space: nowrap;
        }

        .seed-field .form-control,
        .seed-field .form-select {
            height: 38px;
        }

        .seed-field .form-text {
            min-height: 1.25rem;
            margin-top: .35rem;
        }

        .seed-form-grid .col-12,
        .seed-manual-grid .col-12 {
            grid-column: 1 / -1;
        }

        @media (max-width: 991.98px) {
            .seed-form-grid,
            .seed-manual-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .seed-form-grid,
            .seed-manual-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h2 class="mb-1">年度別シード一覧</h2>
            <div class="text-muted">
                前年度ポイントランキング上位者など、年度共通でシード扱いにする選手を管理します。
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tournament_results.rankings') }}" class="btn btn-outline-primary">ポイントランキングへ</a>
            <a href="{{ route('tournaments.index') }}" class="btn btn-outline-secondary">大会一覧へ</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">入力内容を確認してください。</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info">
        <div class="fw-bold mb-1">この画面の役割</div>
        <div>
            通常運用では、年度末に確定したポイントランキングから翌年度のシードを自動生成します。<br>
            例：2025年男子ポイントランキング上位24名 → 2026年男子シード。<br>
            ここで登録された選手は、同じ年度・性別の大会でライセンスNo欄に <strong>S 0524</strong> のように表示されます。
        </div>
    </div>


    @if (($availableRankingYears ?? collect())->isEmpty())
        <div class="alert alert-warning">
            <div class="fw-bold mb-1">ポイントランキング元データがまだ見つかっていません。</div>
            <div>
                年度は入力できますが、指定年度の <code>tournament_results.points</code> が存在しない場合、自動生成時に「対象者が見つかりません」と表示されます。
                先に大会成績一覧で「賞金・ポイント再計算」を実行し、年間ランキングにポイントが出る状態にしてください。
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-bold">前年度ポイントランキングから自動生成</div>
        <div class="card-body">
            <form method="POST" action="{{ route('pro_bowler_seed_lists.generate') }}">
                @csrf

                <div class="seed-form-grid">
                    <div class="seed-field">
                        <label class="form-label">シード年度 <span class="text-danger">*</span></label>
                        <input type="number" name="seed_year" class="form-control"
                               value="{{ old('seed_year', $defaultSeedYear) }}" min="2000" max="2100" required>
                        <div class="form-text">例：2026</div>
                    </div>

                    <div class="seed-field">
                        <label class="form-label">性別 <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            @foreach ($genderLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('gender', 'M') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="seed-field">
                        <label class="form-label">元ランキング年度 <span class="text-danger">*</span></label>
                        <input type="number" name="base_ranking_year" class="form-control" list="ranking-year-options"
                               value="{{ old('base_ranking_year', $defaultBaseRankingYear) }}" min="2000" max="2100" required>
                        <datalist id="ranking-year-options">
                            @foreach ($rankingYears as $rankingYear)
                                <option value="{{ $rankingYear }}"></option>
                            @endforeach
                        </datalist>
                        <div class="form-text">例：2025。候補に無くても直接入力できます。</div>
                    </div>

                    <div class="seed-field">
                        <label class="form-label">上位人数 <span class="text-danger">*</span></label>
                        <input type="number" name="base_top_count" class="form-control"
                               value="{{ old('base_top_count', 24) }}" min="1" max="100" required>
                        <div class="form-text">通常：24</div>
                    </div>

                    <div class="seed-field">
                        <label class="form-label">備考</label>
                        <input type="text" name="notes" class="form-control"
                               value="{{ old('notes') }}"
                               placeholder="例：年度末確定ランキングから自動生成">
                    </div>
                </div>

                <div class="mt-3 d-flex align-items-center gap-3">
                    <button type="submit" class="btn btn-primary">
                        ランキングから年度別シードを生成
                    </button>
                    <span class="text-muted small">
                        同じシード年度・性別で既に登録済みの場合は、ランキング結果で差し替えます。
                    </span>
                </div>
            </form>
        </div>
    </div>

    <details class="mb-4">
        <summary class="fw-bold mb-2">例外対応：ライセンスNo貼り付けで作成する</summary>

        <div class="card mt-2">
            <div class="card-header fw-bold">手入力で年度別シード一覧を登録</div>
            <div class="card-body">
                <form method="POST" action="{{ route('pro_bowler_seed_lists.store') }}">
                    @csrf

                    <div class="seed-manual-grid">
                        <div class="seed-field">
                            <label class="form-label">シード年度 <span class="text-danger">*</span></label>
                            <input type="number" name="seed_year" class="form-control"
                                   value="{{ old('seed_year', $defaultSeedYear) }}" min="2000" max="2100" required>
                        </div>

                        <div class="seed-field">
                            <label class="form-label">性別 <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                @foreach ($genderLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(old('gender', 'M') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="seed-field">
                            <label class="form-label">元ランキング年度 <span class="text-danger">*</span></label>
                            <input type="number" name="base_ranking_year" class="form-control"
                                   value="{{ old('base_ranking_year', $defaultBaseRankingYear) }}" min="2000" max="2100" required>
                        </div>

                        <div class="seed-field">
                            <label class="form-label">上位人数 <span class="text-danger">*</span></label>
                            <input type="number" name="base_top_count" class="form-control"
                                   value="{{ old('base_top_count', 24) }}" min="1" max="100" required>
                        </div>

                        <div class="seed-field">
                            <label class="form-label">参照URL</label>
                            <input type="text" name="source_url" class="form-control"
                                   value="{{ old('source_url') }}"
                                   placeholder="例：https://www.jpba.or.jp/...">
                        </div>

                        <div class="col-12">
                            <label class="form-label">ライセンスNo一覧 <span class="text-danger">*</span></label>
                            <textarea name="license_rows" class="form-control" rows="8" required
                                      placeholder="例：
1423
1452
1443

または公式ランキング表から
1 1423 安里 秀策
2 1452 内藤 広人">{{ old('license_rows') }}</textarea>
                            <div class="form-text">
                                通常は上の自動生成を使ってください。ここはランキング外の緊急対応・移行用です。
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">備考</label>
                            <textarea name="notes" class="form-control" rows="2"
                                      placeholder="例：公式ランキング未反映分を手入力">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-outline-primary">手入力で年度別シードを保存</button>
                    </div>
                </form>
            </div>
        </div>
    </details>

    <div class="card">
        <div class="card-header fw-bold">登録済みの年度別シード一覧</div>
        <div class="card-body p-0">
            @if ($seedLists->isEmpty())
                <div class="p-4 text-center text-muted">
                    年度別シード一覧はまだ登録されていません。
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">シード年度</th>
                                <th style="width: 70px;">性別</th>
                                <th style="width: 120px;">元ランキング</th>
                                <th style="width: 90px;">上位人数</th>
                                <th>登録選手</th>
                                <th>備考</th>
                                <th style="width: 90px;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($seedLists as $seedList)
                                <tr>
                                    <td>{{ $seedList->seed_year }}</td>
                                    <td>{{ $genderLabels[$seedList->gender] ?? $seedList->gender }}</td>
                                    <td>{{ $seedList->base_ranking_year }}</td>
                                    <td>{{ $seedList->base_top_count }}</td>
                                    <td>
                                        @if ($seedList->players->isEmpty())
                                            <span class="text-muted">未登録</span>
                                        @else
                                            <div class="small">
                                                @foreach ($seedList->players->sortBy('priority_order')->take(30) as $player)
                                                    <div>
                                                        {{ $player->seed_rank }}.
                                                        {{ $player->bowler?->name_kanji ?? '選手未特定' }}
                                                        <span class="text-muted">({{ $player->license_no }})</span>
                                                        @if ($player->note)
                                                            <span class="text-muted">/ {{ $player->note }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach

                                                @if ($seedList->players->count() > 30)
                                                    <div class="text-muted">ほか {{ $seedList->players->count() - 30 }} 名</div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="small">
                                        {{ $seedList->notes ?: '-' }}
                                        @if ($seedList->source_url)
                                            <div>
                                                <a href="{{ $seedList->source_url }}" target="_blank" rel="noopener">
                                                    参照
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('pro_bowler_seed_lists.destroy', $seedList) }}"
                                              onsubmit="return confirm('この年度別シード一覧を削除しますか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">削除</button>
                                        </form>
                                    </td>
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
