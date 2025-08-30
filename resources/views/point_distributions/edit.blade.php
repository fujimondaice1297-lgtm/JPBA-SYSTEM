@extends('layouts.app')

@section('content')
<h2>ポイント配分を編集：順位 {{ $pointDistribution->rank }}</h2>

<form method="POST" action="{{ route('tournaments.point_distributions.update', [$tournament->id, $pointDistribution->id]) }}">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label>順位</label>
        <input type="number" name="rank" value="{{ $pointDistribution->rank }}" class="form-control" required>
    </div>

    <div class="mb-3">
        <label>獲得ポイント（pt）</label>
        <input type="number" name="points" value="{{ $pointDistribution->points }}" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">更新</button>
    <a href="{{ route('tournaments.point_distributions.index', $tournament->id) }}" class="btn btn-secondary">戻る</a>
</form>
@endsection
