@extends('layouts.app')

@section('content')
<h2>ポイント配分を作成：{{ $tournament->name }}</h2>

<form method="POST" action="{{ route('tournaments.point_distributions.store', $tournament->id) }}">
    @csrf

    <div class="mb-3">
        <label>テンプレート選択:</label>
        <select name="pattern_id" class="form-select">
            <option value="">カスタム入力</option>
            @foreach($patterns as $pattern)
                <option value="{{ $pattern->id }}">{{ $pattern->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-2">人数指定で自動入力</h5>
            <p class="text-muted mb-2">
                人数を入力して「自動入力」を押すと、最下位を1ポイントとして、上位へ1ポイントずつ増える配分を作成します。<br>
                例：34人なら、1位34P、2位33P、...、34位1P。
            </p>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">ポイントを付ける人数</label>
                    <input type="number" id="autoPointCount" class="form-control" min="1" max="96" placeholder="例：34">
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-primary" onclick="fillStepPoints()">自動入力</button>
                    <button type="button" class="btn btn-outline-danger" onclick="clearAll()">全てクリア</button>
                </div>
            </div>
        </div>
    </div>

    @php
        $max = 96;
        $rowsPerCol = 20;
        $cols = ceil($max / $rowsPerCol);
    @endphp

    <div class="row">
        @for ($col = 0; $col < $cols; $col++)
            <div class="col">
                <table class="table table-bordered">
                    <thead>
                        <tr><th>順位</th><th>ポイント</th><th>表示</th></tr>
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
                                    <input type="number" name="points[]" value="{{ $existing?->points ?? '' }}" class="form-control distribution-value" min="0" data-enabled-target="enabled_{{ $i }}">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" id="enabled_{{ $i }}" name="enabled[]" value="{{ $i }}" {{ $existing ? 'checked' : '' }}>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        @endfor
    </div>

    <button type="submit" class="btn btn-primary">保存</button>
    <a href="{{ route('tournaments.results.index', $tournament) }}" class="btn btn-secondary">大会成績へ戻る</a>
</form>

<script>
    function syncEnabledFromValues() {
        document.querySelectorAll('.distribution-value').forEach(input => {
            const checkbox = document.getElementById(input.dataset.enabledTarget);
            if (!checkbox) return;
            checkbox.checked = input.value !== '';
        });
    }

    function fillStepPoints() {
        const count = parseInt(document.getElementById('autoPointCount').value, 10);
        if (!Number.isInteger(count) || count < 1 || count > 96) {
            alert('1〜96の範囲で人数を入力してください。');
            return;
        }

        document.querySelectorAll('input[name="rank[]"]').forEach((rankInput, index) => {
            const rank = parseInt(rankInput.value, 10);
            const pointInput = document.querySelectorAll('input[name="points[]"]')[index];
            if (!pointInput) return;
            pointInput.value = rank <= count ? String(count - rank + 1) : '';
        });

        syncEnabledFromValues();
    }

    function clearAll() {
        document.querySelectorAll('input[name="points[]"]').forEach(input => input.value = '');
        syncEnabledFromValues();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.distribution-value').forEach(input => {
            input.addEventListener('input', syncEnabledFromValues);
        });
        syncEnabledFromValues();
    });
</script>
@endsection
