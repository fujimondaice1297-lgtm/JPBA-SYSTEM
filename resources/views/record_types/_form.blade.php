@php
    $value = fn (string $field, mixed $default = null) => old($field, $recordType?->{$field} ?? $default);
    $isEdit = $recordType !== null;
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@if ($recordType?->warning)
    <div class="alert alert-warning">{{ $recordType->warning }}</div>
@endif

<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    @if(request('return_to') === 'pro_bowler_edit')
        <input type="hidden" name="return_to" value="pro_bowler_edit">
    @endif

    <div class="card mb-3">
        <div class="card-header">公認記録</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">記録種別 <span class="text-danger">*</span></label>
                    <select name="record_type" class="form-select" required>
                        <option value="perfect" @selected($value('record_type') === 'perfect')>公認パーフェクト</option>
                        <option value="eight_hundred" @selected($value('record_type') === 'eight_hundred')>公認800シリーズ</option>
                        <option value="seven_ten" @selected($value('record_type') === 'seven_ten')>公認7－10メイド</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">状態 <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="candidate" @selected($value('status', 'candidate') === 'candidate')>確認待ち</option>
                        <option value="confirmed" @selected($value('status') === 'confirmed')>確認済み</option>
                        <option value="rejected" @selected($value('status') === 'rejected')>却下</option>
                        <option value="void" @selected($value('status') === 'void')>無効</option>
                    </select>
                    <small class="text-muted">確認済みにすると、未設定の場合は公認番号を自動採番します。</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">登録区分 <span class="text-danger">*</span></label>
                    <select name="registration_mode" class="form-select" required>
                        <option value="historical_backfill" @selected($value('registration_mode', 'historical_backfill') === 'historical_backfill')>過去明細（総数へ加算しない）</option>
                        <option value="new_achievement" @selected($value('registration_mode') === 'new_achievement')>新規達成（確認時に総数へ加算）</option>
                    </select>
                </div>

                @if ($isEdit)
                    <div class="col-md-6">
                        <label class="form-label">選手</label>
                        <input class="form-control" value="{{ $recordType->proBowler->name_kanji ?? '不明' }}（{{ $recordType->proBowler->license_no ?? '-' }}）" readonly>
                    </div>
                @else
                    <div class="col-md-6">
                        <label class="form-label">ライセンス番号 <span class="text-danger">*</span></label>
                        <input type="text" name="pro_bowler_license_no" value="{{ old('pro_bowler_license_no') }}" class="form-control" required placeholder="例：M00001219">
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label">公認番号</label>
                    <input type="text" name="certification_number" value="{{ $value('certification_number') }}" class="form-control">
                    <small class="text-muted">空欄のまま確認すると設定済みの次番号を採番。後から編集できます。</small>
                </div>

                <div class="col-md-3">
                    <label class="form-label">達成年月日</label>
                    <input type="date" name="awarded_on" value="{{ optional($recordType?->awarded_on)->format('Y-m-d') ?: old('awarded_on') }}" class="form-control">
                </div>
                <div class="col-md-9">
                    <label class="form-label">大会名 <span class="text-danger">*</span></label>
                    <input type="text" name="tournament_name" value="{{ $value('tournament_name') }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ラウンド</label>
                    <input type="text" name="stage" value="{{ $value('stage') }}" class="form-control" placeholder="例：予選、準決勝">
                </div>
                <div class="col-md-4">
                    <label class="form-label">シフト</label>
                    <input type="text" name="shift" value="{{ $value('shift') }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">性別区分</label>
                    <select name="gender" class="form-select">
                        <option value="">選手ライセンスから判定</option>
                        <option value="M" @selected($value('gender') === 'M')>男子</option>
                        <option value="F" @selected($value('gender') === 'F')>女子</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">達成ゲーム</label>
                    <input type="text" name="game_numbers" value="{{ $value('game_numbers') }}" class="form-control" placeholder="例：予選3ゲーム目">
                </div>
                <div class="col-md-6">
                    <label class="form-label">達成フレーム（7－10メイド）</label>
                    <input type="text" name="frame_number" value="{{ $value('frame_number') }}" class="form-control" placeholder="例：7フレーム目">
                </div>
                <div class="col-md-6">
                    <label class="form-label">800シリーズ名</label>
                    <input type="text" name="series_label" value="{{ $value('series_label') }}" class="form-control" placeholder="例：予選第1シリーズ">
                </div>
                <div class="col-md-2">
                    <label class="form-label">開始G</label>
                    <input type="number" name="series_start_game" value="{{ $value('series_start_game') }}" class="form-control" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">終了G</label>
                    <input type="number" name="series_end_game" value="{{ $value('series_end_game') }}" class="form-control" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">3G合計</label>
                    <input type="number" name="series_total" value="{{ $value('series_total') }}" class="form-control" min="0" max="900">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">取得元・根拠</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">取得方法</label>
                    <input type="text" name="source_type" value="{{ $value('source_type', 'manual') }}" class="form-control">
                </div>
                <div class="col-md-8">
                    <label class="form-label">表示名</label>
                    <input type="text" name="source_label" value="{{ $value('source_label') }}" class="form-control" placeholder="例：JPBA公式大会ページ">
                </div>
                <div class="col-12">
                    <label class="form-label">根拠URL</label>
                    <input type="url" name="source_url" value="{{ $value('source_url') }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">根拠テキスト</label>
                    <textarea name="evidence_text" class="form-control" rows="4">{{ $value('evidence_text') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">注記</label>
                    <textarea name="notes" class="form-control" rows="4">{{ $value('notes') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">確認上の警告</label>
                    <textarea name="warning" class="form-control" rows="2">{{ $value('warning') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">保存</button>
        <a
            href="{{ request('return_to') === 'pro_bowler_edit' && $recordType?->pro_bowler_id
                ? route('pro_bowlers.edit', $recordType->pro_bowler_id)
                : route('record_types.index') }}"
            class="btn btn-outline-secondary"
        >キャンセル</a>
    </div>
</form>
