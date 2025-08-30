@extends('layouts.app')

@section('content')
<div class="container">
    <h2>{{ $tournament->name }} - 成績一括登録</h2>

    <form action="{{ route('tournaments.results.store', $tournament->id) }}" method="POST">
        @csrf

        @foreach ($players as $i => $player)
            <div class="card my-3 p-3">
                <h5>{{ $player->name_kanji }}（{{ $player->license_no }}）</h5>

                <input type="hidden" name="results[{{ $i }}][pro_bowler_license_no]" value="{{ $player->license_no }}">

                <div class="form-group">
                    <label>順位</label>
                    <input type="number" name="results[{{ $i }}][ranking]" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>トータルピン</label>
                    <input type="number" name="results[{{ $i }}][total_pin]" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>ゲーム数</label>
                    <input type="number" name="results[{{ $i }}][games]" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>年度</label>
                    <input type="number" name="results[{{ $i }}][ranking_year]" class="form-control" value="{{ now()->year }}" required>
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-warning">保存する</button>
    </form>
</div>
@endsection
