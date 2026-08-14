@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">公認記録詳細</h2>
        <div>
            <a href="{{ route('record_types.edit', $recordType) }}" class="btn btn-primary">編集</a>
            <a href="{{ route('record_types.index') }}" class="btn btn-outline-secondary">一覧へ</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">状態</dt>
                <dd class="col-md-9">{{ $recordType->status_label }}</dd>
                <dt class="col-md-3">選手</dt>
                <dd class="col-md-9">{{ $recordType->proBowler->name_kanji ?? '不明' }}（{{ $recordType->proBowler->license_no ?? '-' }}）</dd>
                <dt class="col-md-3">記録種別</dt>
                <dd class="col-md-9">{{ $recordType->record_type_label }}</dd>
                <dt class="col-md-3">達成日</dt>
                <dd class="col-md-9">{{ optional($recordType->awarded_on)->format('Y/m/d') ?: '未確認' }}</dd>
                <dt class="col-md-3">大会</dt>
                <dd class="col-md-9">{{ $recordType->tournament_name }}</dd>
                <dt class="col-md-3">ラウンド・シフト</dt>
                <dd class="col-md-9">{{ $recordType->stage ?: '-' }}{{ $recordType->shift ? ' / '.$recordType->shift : '' }}</dd>
                <dt class="col-md-3">達成位置</dt>
                <dd class="col-md-9">
                    {{ $recordType->series_label ?: $recordType->game_numbers ?: '未確認' }}
                    {{ $recordType->frame_number ? ' / '.$recordType->frame_number : '' }}
                </dd>
                @if ($recordType->record_type === 'eight_hundred')
                    <dt class="col-md-3">シリーズ</dt>
                    <dd class="col-md-9">
                        {{ $recordType->series_start_game }}～{{ $recordType->series_end_game }}G /
                        合計 {{ $recordType->series_total ?? '未確認' }}
                        @if ($recordType->series_scores)
                            （{{ collect($recordType->series_scores)->map(fn ($score, $game) => $game.'G: '.$score)->implode(' / ') }}）
                        @endif
                    </dd>
                @endif
                <dt class="col-md-3">公認番号</dt>
                <dd class="col-md-9">{{ $recordType->certification_number ?: '未設定' }}</dd>
                <dt class="col-md-3">登録区分</dt>
                <dd class="col-md-9">{{ $recordType->registration_mode === 'new_achievement' ? '新規達成（総数へ加算）' : '過去明細（総数へ加算しない）' }}</dd>
                <dt class="col-md-3">取得元</dt>
                <dd class="col-md-9">
                    {{ $recordType->source_label ?: $recordType->source_type ?: '-' }}
                    @if ($recordType->source_url)
                        / <a href="{{ $recordType->source_url }}" target="_blank" rel="noopener">根拠を開く</a>
                    @endif
                </dd>
                <dt class="col-md-3">根拠・注記</dt>
                <dd class="col-md-9">
                    @if ($recordType->warning)<div class="text-danger">{{ $recordType->warning }}</div>@endif
                    <div class="text-break">{{ $recordType->evidence_text ?: '-' }}</div>
                    @if ($recordType->notes)<div class="mt-2">{{ $recordType->notes }}</div>@endif
                </dd>
            </dl>
        </div>
    </div>
</div>
@endsection
