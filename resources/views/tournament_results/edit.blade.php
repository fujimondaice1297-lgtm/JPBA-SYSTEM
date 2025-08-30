@extends('layouts.app')

@section('content')
<div class="container">
    <h2>大会成績 編集</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('tournament_results.update', $result->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="pro_bowler_license_no" class="form-label">選手</label>
            <select name="pro_bowler_license_no" id="pro_bowler_license_no" class="form-select">
                @foreach ($players as $player)
                    <option value="{{ $player->license_no }}" {{ $player->license_no == $result->pro_bowler_license_no ? 'selected' : '' }}>
                        {{ $player->name_kanji }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="tournament_id" class="form-label">大会</label>
            <select name="tournament_id" id="tournament_id" class="form-select">
                @foreach ($tournaments as $tournament)
                    <option value="{{ $tournament->id }}" {{ $tournament->id == $result->tournament_id ? 'selected' : '' }}>
                        {{ $tournament->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="ranking" class="form-label">順位</label>
            <input type="number" name="ranking" id="ranking" class="form-control" value="{{ $result->ranking }}">
        </div>

        <div class="mb-3">
            <label for="total_pin" class="form-label">トータルピン</label>
            <input type="number" name="total_pin" id="total_pin" class="form-control" value="{{ $result->total_pin }}">
        </div>

        <div class="mb-3">
            <label for="games" class="form-label">ゲーム数</label>
            <input type="number" name="games" id="games" class="form-control" value="{{ $result->games }}">
        </div>

        <div class="mb-3">
            <label for="ranking_year" class="form-label">ランキング年度</label>
            <input type="number" name="ranking_year" id="ranking_year" class="form-control" value="{{ $result->ranking_year }}">
        </div>

        <button type="submit" class="btn btn-primary">更新</button>
        <a href="{{ route('tournament_results.index') }}" class="btn btn-secondary">戻る</a>
    </form>
</div>
@endsection
