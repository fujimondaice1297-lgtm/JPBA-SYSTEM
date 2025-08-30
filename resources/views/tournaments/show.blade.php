@extends('layouts.app')

@section('content')
<div class="bg-white p-4 border border-gray-400 shadow-xl rounded">

    {{-- タイトル＋戻るボタン 横並び --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-2xl fw-bold border-bottom border-danger pb-2 text-danger">大会詳細</h1>
        <a href="{{ route('tournaments.index') }}" class="btn btn-danger">大会一覧へ戻る</a>
        <a href="{{ route('tournament_results.index') }}" class="btn btn-warning">大会成績一覧へ戻る</a>
        <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-primary">編集</a>
    </div>

    {{-- 詳細テーブル --}}
    <div class="table-responsive">
        <table class="table table-bordered w-100">
            <tbody>
                <tr class="table-danger"><th>大会名</th><td>{{ $tournament->name }}</td></tr>
                <tr><th>開始日</th><td>{{ optional($tournament->start_date)->format('Y-m-d') }}</td></tr>
                <tr><th>終了日</th><td>{{ optional($tournament->end_date)->format('Y-m-d') }}</td></tr>
                <tr><th>申込開始日</th><td>{{ optional($tournament->entry_start)->format('Y-m-d') }}</td></tr>
                <tr><th>申込締切日</th><td>{{ optional($tournament->entry_end)->format('Y-m-d') }}</td></tr>
                <tr><th>会場名</th><td>{{ $tournament->venue_name }}</td></tr>
                <tr class="table-light"><th>会場住所</th><td>{{ $tournament->venue_address }}</td></tr>
                <tr><th>電話番号</th><td>{{ $tournament->venue_tel }}</td></tr>
                <tr class="table-light"><th>FAX</th><td>{{ $tournament->venue_fax }}</td></tr>
                <tr><th>種別</th><td>
                    @if($tournament->gender === 'M') 男子
                    @elseif($tournament->gender === 'F') 女子
                    @else 男女/未設定
                    @endif
                </td></tr>
                <tr><th>大会区分</th><td>
                    @if($tournament->official_type === 'official') 公認
                    @elseif($tournament->official_type === 'approved') 承認
                    @else その他
                    @endif
                </td></tr>
                <tr><th>主催</th><td>{{ $tournament->host }}</td></tr>
                <tr class="table-light"><th>特別協賛</th><td>{{ $tournament->special_sponsor }}</td></tr>
                <tr><th>後援</th><td>{{ $tournament->support }}</td></tr>
                <tr class="table-light"><th>協賛</th><td>{{ $tournament->sponsor }}</td></tr>
                <tr><th>主管</th><td>{{ $tournament->supervisor }}</td></tr>
                <tr class="table-light"><th>公認</th><td>{{ $tournament->authorized_by }}</td></tr>
                <tr><th>TV放映</th><td>{{ $tournament->broadcast }}</td></tr>
                <tr class="table-light"><th>配信</th><td>{{ $tournament->streaming }}</td></tr>
                <tr><th>賞金</th><td>{{ $tournament->prize }}</td></tr>
                <tr class="table-light"><th>観客数</th><td>{{ $tournament->audience }}</td></tr>
                <tr><th>参加条件</th><td>{!! nl2br(e($tournament->entry_conditions)) !!}</td></tr>
                <tr class="table-light"><th>資料</th><td>{!! nl2br(e($tournament->materials)) !!}</td></tr>
                <tr><th>前年大会</th><td>{{ $tournament->previous_event }}</td></tr>
                <tr class="table-light">
                    <th>ポスター画像</th>
                    <td>
                        @if($tournament->image_path)
                            <img src="{{ asset('storage/' . $tournament->image_path) }}" alt="ポスター" class="img-fluid rounded shadow">
                        @else
                            画像なし
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
