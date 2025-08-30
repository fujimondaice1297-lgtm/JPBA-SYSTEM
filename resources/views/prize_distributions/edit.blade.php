@extends('layouts.app')

@section('content')
<h2>賞金配分を編集：順位 {{ $prize_distribution->rank }}</h2>

<form method="POST" action="{{ route('tournaments.prize_distributions.update', [$tournament->id, $prize_distribution->id]) }}">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label>順位</label>
        <input type="number" name="rank" value="{{ $prize_distribution->rank }}" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>賞金額（円）</label>
        <input type="number" name="amount" value="{{ $prize_distribution->amount }}" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">更新</button>
    <a href="{{ route('tournaments.prize_distributions.index', $tournament->id) }}" class="btn btn-secondary">戻る</a>
</form>
@endsection
