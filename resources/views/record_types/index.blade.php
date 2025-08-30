@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <h2>記録種別データ</h2>

    <!-- 検索フォーム（必要に応じて） -->
    <form method="GET" action="{{ route('record_types.index') }}" class="row g-3 mb-4">
        <div class="col-md-3">
            <input type="text" name="player_identifier" class="form-control" placeholder="選手名またはライセンス番号">
        </div>
        <div class="col-md-2">
            <select name="record_type" class="form-select">
                <option value="">種類を選択</option>
                <option value="perfect">パーフェクト</option>
                <option value="seven_ten">7-10</option>
                <option value="eight_hundred">800</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="tournament_name" class="form-control" placeholder="大会名">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary">検索</button>
            <a href="{{ route('record_types.index') }}" class="btn btn-warning">リセット</a>
            <a href="{{ route('record_types.create') }}" class="btn btn-success">新規登録</a>
            <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
        </div>
    </form>

    <!-- テーブル表示 -->
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>選手名</th>
                <th>記録種別</th>
                <th>大会名</th>
                <th>達成ゲーム</th>
                <th>フレーム番号</th>
                <th>公認番号</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr>
                    <td>
                        <a href="{{ route('record_types.edit', $record->id) }}">
                            {{ $record->proBowler->name_kanji ?? '不明' }}
                        </a>
                    </td>
                    <td>
                        @switch($record->record_type)
                            @case('perfect') パーフェクト @break
                            @case('seven_ten') 7-10スプリットメイク @break
                            @case('eight_hundred') 800シリーズ @break
                            @default - 
                        @endswitch
                    </td>
                    <td>{{ $record->tournament_name }}</td>
                    <td>{{ $record->game_numbers }}</td>
                    <td>{{ $record->frame_number ?? '-' }}</td>
                    <td>{{ $record->certification_number }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">データが存在しません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
