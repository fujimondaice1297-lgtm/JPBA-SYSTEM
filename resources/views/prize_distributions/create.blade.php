@extends('layouts.app')

@section('content')
<h2>賞金配分を作成：{{ $tournament->name }}</h2>

<form method="POST" action="{{ route('tournaments.prize_distributions.store', $tournament->id) }}">
    @csrf

    <div>
        <label>テンプレート選択:</label>
        <select name="pattern_id" class="form-select">
            <option value="">カスタム入力</option>
            @foreach($patterns as $pattern)
                <option value="{{ $pattern->id }}">{{ $pattern->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAll(true)">全て表示</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">全て非表示</button>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearAll()">全てクリア</button>
    </div>

    <script>
        function toggleAll(state) {
            document.querySelectorAll('input[name="enabled[]"]').forEach(cb => cb.checked = state);
        function clearAll() {
            document.querySelectorAll('input[name="amount[]"]').forEach(input => input.value = '');}
        }
    </script>

    @php
        $max = 96;
        $rowsPerCol = 20;
        $cols = ceil($max / $rowsPerCol); // = 5
    @endphp

    <div class="row">
        @for ($col = 0; $col < $cols; $col++)
            <div class="col">
                <table class="table table-bordered">
                    <thead>
                        <tr><th>順位</th><th>金額</th><th>表示</th></tr>
                    </thead>
                    <tbody>
                        @for ($i = 1 + $col * $rowsPerCol; $i <= min(($col + 1) * $rowsPerCol, $max); $i++)
                            @php
                                $existing = $existingDistributions->firstWhere('rank', $i) ?? null;
                            @endphp
                            <tr>
                                <td>
                                    <input type="number" name="rank[]" value="{{ $i }}" class="form-control" readonly>
                                </td>
                                <td>
                                    <input type="number" name="amount[]" value="{{ $existing?->amount ?? '' }}" class="form-control">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="enabled[]" value="{{ $i }}" {{ $existing ? 'checked' : '' }}>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        @endfor
    </div>

    <button type="submit" class="btn btn-primary">保存</button>
    <a href="{{ route('tournaments.index') }}" class="btn btn-secondary ">大会一覧に戻る</a>
</form>
@endsection

