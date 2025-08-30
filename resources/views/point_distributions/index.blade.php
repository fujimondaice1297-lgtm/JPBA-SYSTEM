@extends('layouts.app')

@section('content')
<div class="container">
    <h2>ポイント配分</h2>
    <p>大会名: {{ $tournament->name }}</p>

    
    <a href="{{ route('tournaments.point_distributions.create', $tournament->id) }}" class="btn btn-success">新規作成</a>
    <a href="{{ route('tournaments.index') }}" class="btn btn-secondary ">大会一覧に戻る</a>
    <a href="{{ route('tournaments.point_distributions.create', $tournament->id) }}" class="btn btn-success">順位を追加する</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>順位</th>
                <th>ポイント</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pointDistributions as $pd)
                <tr>
                    <td>{{ $pd->rank }}</td>
                    <td>{{ number_format($pd->points) }} pt</td>
                    <td>
                        <a href="{{ route('tournaments.point_distributions.edit', [$tournament->id, $pd->id]) }}" class="btn btn-sm btn-primary">編集</a>
                        <form action="{{ route('tournaments.point_distributions.destroy', [$tournament->id, $pd->id]) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" onclick="return confirm('本当に削除しますか？')">削除</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
