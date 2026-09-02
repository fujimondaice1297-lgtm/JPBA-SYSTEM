@extends('layouts.app')

@section('content')
@php
    $statusLabels = ['draft' => '準備中', 'live' => '速報公開中', 'final' => '結果確定'];
    $finalLabels = ['pending' => '未確定', 'passed' => '合格', 'not_passed' => '不合格', 'withdrawn' => '棄権'];
    $stageResultLabels = ['pending' => '未確定', 'passed' => '合格', 'not_passed' => '不合格', 'withdrawn' => '棄権', 'exempt' => '免除'];
    $stageLabels = ['first' => '第1次', 'second' => '第2次', 'third' => '第3次'];
@endphp
<style>
    .protest-work { max-width: 1320px; margin: 0 auto; }
    .protest-head { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; padding: 22px 24px; border-radius: 18px; background: #183b69; color: #fff; }
    .protest-head h1 { margin: 0 0 6px; font-size: 1.65rem; font-weight: 800; }
    .protest-head p { margin: 0; color: rgba(255,255,255,.8); }
    .protest-steps { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin: 18px 0; }
    .protest-step { padding: 13px; border: 1px solid #dfe5ec; border-radius: 12px; background: #fff; font-size: .8rem; font-weight: 700; }
    .protest-step span { display: block; margin-bottom: 3px; color: #57718d; font-size: .7rem; }
    .protest-section { margin-top: 18px; border: 1px solid #dfe5ec; border-radius: 15px; background: #fff; overflow: hidden; }
    .protest-section > header { padding: 16px 20px; border-bottom: 1px solid #e7ebf0; background: #f8fafc; }
    .protest-section > header h2 { margin: 0; font-size: 1.08rem; font-weight: 800; }
    .protest-section > header p { margin: 4px 0 0; color: #667085; font-size: .82rem; }
    .protest-body { padding: 20px; }
    .protest-session { margin-bottom: 12px; padding: 15px; border: 1px solid #e1e6ed; border-radius: 12px; }
    .privacy-note { padding: 12px 14px; border-left: 4px solid #a83d59; background: #fff4f6; color: #70253b; font-size: .84rem; }
    .exemption-note { padding: 12px 14px; border-left: 4px solid #c88619; background: #fff9e8; color: #6d4b0b; font-size: .84rem; }
    textarea.data-paste { min-height: 125px; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .82rem; }
    @media (max-width: 900px) { .protest-steps { grid-template-columns: repeat(2, 1fr); } .protest-head { flex-direction: column; } }
</style>

<div class="protest-work">
    <header class="protest-head">
        <div>
            <h1>{{ $event->year }}年度 プロテスト運用</h1>
            <p>{{ $event->name }}　<span class="badge bg-light text-dark">{{ $statusLabels[$event->status] ?? $event->status }}</span></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-light" href="{{ route('pro_tests.index') }}">年度一覧</a>
            @if($event->sessions->contains(fn ($session) => $session->published_at) || $event->final_results_published_at)
                <a class="btn btn-warning" href="{{ route('public.pro_tests.show', $event) }}" target="_blank">一般公開を確認</a>
            @endif
        </div>
    </header>

    <div class="protest-steps">
        <div class="protest-step"><span>STEP 1</span>基本情報</div>
        <div class="protest-step"><span>STEP 2</span>実施日</div>
        <div class="protest-step"><span>STEP 3</span>受験者</div>
        <div class="protest-step"><span>STEP 4</span>得点入力</div>
        <div class="protest-step"><span>STEP 5</span>速報公開</div>
        <div class="protest-step"><span>STEP 6</span>合格者公開</div>
    </div>

    <div class="privacy-note">一般速報には受験番号・氏名・フリガナ・居住都道府県・利き腕・得点だけを保存します。年齢、生年月日、住所、電話、メールなどはこの運用データに保持しません。</div>

    <section class="protest-section">
        <header><h2>1. 基本情報</h2><p>日程と一般ページの説明を設定します。</p></header>
        <div class="protest-body">
            <form method="POST" action="{{ route('pro_tests.update', $event) }}" class="row g-3">
                @csrf @method('PUT')
                <div class="col-md-6"><label class="form-label">名称</label><input class="form-control" name="name" value="{{ old('name', $event->name) }}" required></div>
                <div class="col-md-3"><label class="form-label">開始日</label><input class="form-control" type="date" name="start_date" value="{{ old('start_date', optional($event->start_date)->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label">終了日</label><input class="form-control" type="date" name="end_date" value="{{ old('end_date', optional($event->end_date)->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label">申込開始</label><input class="form-control" type="date" name="application_start" value="{{ old('application_start', optional($event->application_start)->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label">申込締切</label><input class="form-control" type="date" name="application_end" value="{{ old('application_end', optional($event->application_end)->format('Y-m-d')) }}"></div>
                <div class="col-md-3"><label class="form-label">男子期別</label><input class="form-control" name="male_generation" value="{{ old('male_generation', $event->male_generation) }}" placeholder="例：第65期"></div>
                <div class="col-md-3"><label class="form-label">女子期別</label><input class="form-control" name="female_generation" value="{{ old('female_generation', $event->female_generation) }}" placeholder="例：第58期"></div>
                <div class="col-12"><label class="form-label">一般向け説明</label><textarea class="form-control" name="public_summary" rows="3">{{ old('public_summary', $event->public_summary) }}</textarea></div>
                <div class="col-12"><button class="btn btn-primary">基本情報を保存</button></div>
            </form>
        </div>
    </section>

    <section class="protest-section">
        <header><h2>2. 実施日を登録</h2><p>男女・第1次/第2次/第3次・日別にゲーム番号を設定します。</p></header>
        <div class="protest-body">
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead><tr><th>順</th><th>区分</th><th>日付</th><th>会場</th><th>ゲーム</th><th>得点</th><th>公開</th></tr></thead>
                    <tbody>
                    @forelse($event->sessions as $session)
                        <tr>
                            <td>{{ $session->sort_order }}</td><td>{{ $session->display_name }}</td><td>{{ optional($session->test_date)->format('Y/n/j') ?: '-' }}</td><td>{{ $session->venue ?: '-' }}</td>
                            <td>{{ $session->game_start }}〜{{ $session->game_end }}G</td><td>{{ $session->scores_count }}件</td>
                            <td>@if($session->latestPublication)<span class="badge bg-primary">第{{ $session->latestPublication->revision }}版</span>@else<span class="text-muted">未公開</span>@endif</td>
                        </tr>
                    @empty<tr><td colspan="7" class="text-muted">実施日が未登録です。</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('pro_tests.sessions.store', $event) }}" class="row g-2">
                @csrf
                <div class="col-md-1"><label class="form-label">男女</label><select class="form-select" name="gender"><option value="M">男子</option><option value="F">女子</option></select></div>
                <div class="col-md-2"><label class="form-label">段階</label><select class="form-select" name="stage_code"><option value="first">第1次</option><option value="second">第2次</option><option value="third">第3次</option></select></div>
                <div class="col-md-2"><label class="form-label">表示名</label><input class="form-control" name="stage_label" value="第1次テスト" required></div>
                <div class="col-md-1"><label class="form-label">日目</label><input class="form-control" type="number" name="day_number" value="1" min="1" required></div>
                <div class="col-md-2"><label class="form-label">実施日</label><input class="form-control" type="date" name="test_date"></div>
                <div class="col-md-2"><label class="form-label">会場</label><input class="form-control" name="venue"></div>
                <div class="col-md-1"><label class="form-label">開始G</label><input class="form-control" type="number" name="game_start" value="1" min="1" required></div>
                <div class="col-md-1"><label class="form-label">終了G</label><input class="form-control" type="number" name="game_end" value="15" min="1" required></div>
                <div class="col-md-2"><label class="form-label">合格AVG</label><input class="form-control" type="number" step="0.01" name="pass_average" placeholder="最終日のみ"></div>
                <div class="col-md-2"><label class="form-label">表示順</label><input class="form-control" type="number" name="sort_order" value="10" min="0" required></div>
                <div class="col-md-3 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_stage_final" value="1"> この段階の最終日</label></div>
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary">実施日を追加</button></div>
            </form>
        </div>
    </section>

    <section class="protest-section">
        <header><h2>3. 受験者・免除区分を一括登録</h2><p>通常受験と協会承認済みの免除者を、開始段階を含めて登録します。</p></header>
        <div class="protest-body">
            <div class="exemption-note mb-3">
                前年の第2次不合格による第1次免除は、翌年度の1回だけ有効です。その免除を利用した年に第2次で再び不合格となった場合、次年度は第1次から登録してください。アマチュア好成績と公式戦優勝の特例は、協会承認後に登録します。
            </div>
            <form method="POST" action="{{ route('pro_tests.candidates.import', $event) }}">
                @csrf
                <label class="form-label">受験番号, 性別, 氏名, フリガナ, 居住都道府県, 利き腕, 開始段階, 免除理由, 前年受験番号, 承認メモ</label>
                <textarea class="form-control data-paste" name="candidate_rows" placeholder="M001,男子,日本 太郎,ニホン タロウ,東京都,右,第1次,通常受験,,&#10;M002,男子,再受験 次郎,サイジュケン ジロウ,大阪府,右,第2次,前年第2次不合格,M010,&#10;M003,男子,特例 三郎,トクレイ サブロウ,愛知県,右,第3次,プロ公式戦アマ優勝,,承認大会名">{{ old('candidate_rows') }}</textarea>
                <div class="form-text">開始段階と免除理由を省略した従来形式は「第1次・通常受験」として扱います。前年免除では前年受験番号が必須です。</div>
                <button class="btn btn-primary mt-2">受験者を取り込む</button>
            </form>

            @if($eligiblePreviousCandidates->isNotEmpty())
                <div class="mt-4">
                    <h3 class="h6 fw-bold">翌年度の第1次免除に利用できる前年第2次不合格者</h3>
                    <p class="small text-muted">下記の受験番号を「前年受験番号」欄へ入力します。一度紐付けると別の受験者には利用できません。</p>
                    <div class="table-responsive" style="max-height:220px">
                        <table class="table table-sm"><thead><tr><th>年度</th><th>受験番号</th><th>区分</th><th>氏名</th></tr></thead><tbody>
                        @foreach($eligiblePreviousCandidates as $candidate)
                            <tr><td>{{ $candidate->event->year }}</td><td>{{ $candidate->exam_number }}</td><td>{{ $candidate->gender_label }}</td><td>{{ $candidate->name }}</td></tr>
                        @endforeach
                        </tbody></table>
                    </div>
                </div>
            @endif

            <div class="row mt-4">
                @foreach([['男子', $maleCandidates], ['女子', $femaleCandidates]] as [$label, $candidates])
                    <div class="col-md-6">
                        <h3 class="h6 fw-bold">{{ $label }} {{ $candidates->count() }}名</h3>
                        <div class="table-responsive" style="max-height:340px">
                            <table class="table table-sm"><thead><tr><th>受験番号</th><th>氏名</th><th>開始</th><th>理由</th><th>段階別結果</th><th>最終</th></tr></thead><tbody>
                            @forelse($candidates as $candidate)
                                @php($results = $candidate->stageResults->keyBy('stage_code'))
                                <tr>
                                    <td>{{ $candidate->exam_number }}</td>
                                    <td>{{ $candidate->name }}</td>
                                    <td>{{ $candidate->entry_stage_label }}</td>
                                    <td><span title="{{ $candidate->exemption_note }}">{{ $candidate->entry_reason_label }}</span>@if($candidate->previousCandidate)<br><small>前年：{{ $candidate->previousCandidate->exam_number }}</small>@endif</td>
                                    <td class="small">
                                        @foreach($stageLabels as $code => $stageLabel)
                                            {{ $stageLabel }}：{{ $stageResultLabels[$results->get($code)?->result] ?? '未確定' }}@if(!$loop->last)<br>@endif
                                        @endforeach
                                    </td>
                                    <td>{{ $finalLabels[$candidate->final_result] ?? $candidate->final_result }}</td>
                                </tr>
                            @empty<tr><td colspan="6" class="text-muted">未登録</td></tr>@endforelse
                            </tbody></table>
                        </div>
                    </div>
                @endforeach
            </div>

            <hr class="my-4">
            <form method="POST" action="{{ route('pro_tests.stage_results.import', $event) }}">
                @csrf
                <label class="form-label fw-bold">段階別結果の確定・訂正</label>
                <p class="small text-muted">最終日を合格AVG付きで速報公開すると、全ゲーム入力済みの受験者は自動判定されます。棄権や例外、訂正はここで上書きできます。</p>
                <label class="form-label">受験番号, 段階, 結果, 備考</label>
                <textarea class="form-control data-paste" name="stage_result_rows" placeholder="M001,第1次,合格,&#10;M002,第2次,不合格,翌年度第1次免除対象">{{ old('stage_result_rows') }}</textarea>
                <button class="btn btn-outline-primary mt-2">段階別結果を保存</button>
            </form>
        </div>
    </section>

    <section class="protest-section">
        <header><h2>4・5. 得点入力と速報公開</h2><p>得点を貼り付けて確認後、管理者が公開します。再公開すると改訂版になります。</p></header>
        <div class="protest-body">
            @forelse($event->sessions as $session)
                <details class="protest-session" @if($loop->first) open @endif>
                    <summary class="fw-bold">{{ $session->display_name }}（{{ $session->game_start }}〜{{ $session->game_end }}G） @if($session->latestPublication)<span class="badge bg-primary">公開第{{ $session->latestPublication->revision }}版</span>@endif</summary>
                    <div class="row g-3 mt-1">
                        <div class="col-lg-8">
                            <form method="POST" action="{{ route('pro_tests.sessions.scores.import', [$event, $session]) }}">
                                @csrf
                                <label class="form-label">受験番号, {{ collect(range($session->game_start, $session->game_end))->map(fn ($game) => $game.'G')->implode(', ') }}</label>
                                <textarea class="form-control data-paste" name="score_rows" placeholder="M001,200,210,225"></textarea>
                                <button class="btn btn-primary mt-2">得点を保存</button>
                            </form>
                        </div>
                        <div class="col-lg-4">
                            <div class="alert alert-warning mb-2">公開ボタンを押すまで一般ページは変わりません。公開前に入力件数を確認してください。</div>
                            @if(auth()->user()?->isAdmin())
                                <form method="POST" action="{{ route('pro_tests.sessions.publish', [$event, $session]) }}" onsubmit="return confirm('現在の集計を一般速報として公開しますか？')">
                                    @csrf
                                    <button class="btn btn-success">速報を確認済みとして公開</button>
                                </form>
                            @else
                                <span class="text-muted">公開操作は管理者のみ行えます。</span>
                            @endif
                        </div>
                    </div>
                    @php($previewRows = $sessionPreviews->get($session->id, []))
                    @if(count($previewRows))
                        <div class="table-responsive mt-3" style="max-height:320px">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>順位</th><th>受験番号</th><th>氏名</th><th>当日ピン</th><th>累計G</th><th>累計ピン</th><th>AVG</th><th>判定</th></tr></thead>
                                <tbody>
                                @foreach($previewRows as $row)
                                    <tr><td>{{ $row['rank'] }}</td><td>{{ $row['exam_number'] }}</td><td>{{ $row['name'] }}</td><td>{{ number_format(array_sum($row['session_scores'])) }}</td><td>{{ $row['games'] }}</td><td>{{ number_format($row['total_pin']) }}</td><td>{{ number_format($row['average'], 2) }}</td><td>{{ $row['result_label'] ?: '-' }}</td></tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mt-3 mb-0">保存済みの得点はありません。</p>
                    @endif
                </details>
            @empty
                <p class="text-muted mb-0">先に実施日を登録してください。</p>
            @endforelse
        </div>
    </section>

    <section class="protest-section">
        <header><h2>6. 最終結果・合格者公開</h2><p>最終結果は内部で全員分を管理しますが、一般ページに公開するのは合格者だけです。</p></header>
        <div class="protest-body row g-4">
            <div class="col-lg-8">
                <form method="POST" action="{{ route('pro_tests.final_results.import', $event) }}">
                    @csrf
                    <label class="form-label">受験番号, 結果（合格・不合格・棄権・未確定）, ライセンス番号（任意）</label>
                    <textarea class="form-control data-paste" name="final_result_rows" placeholder="M001,合格,M00001500"></textarea>
                    <button class="btn btn-primary mt-2">最終結果を保存</button>
                </form>
            </div>
            <div class="col-lg-4">
                <p>合格者 {{ $event->candidates->where('final_result', 'passed')->count() }}名 / 選手マスタ紐付け済み {{ $event->candidates->where('final_result', 'passed')->whereNotNull('pro_bowler_id')->count() }}名</p>
                @if(auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('pro_tests.final_results.publish', $event) }}" onsubmit="return confirm('合格者一覧を一般公開しますか？不合格者は公開されません。')">
                        @csrf
                        <button class="btn btn-success">合格者一覧を公開</button>
                    </form>
                @endif
                @if($event->latestFinalResultPublication)<p class="text-success mt-2 mb-0">公開第{{ $event->latestFinalResultPublication->revision }}版：{{ $event->latestFinalResultPublication->published_at->format('Y/n/j H:i') }}</p>@endif
            </div>
        </div>
    </section>
</div>
@endsection
