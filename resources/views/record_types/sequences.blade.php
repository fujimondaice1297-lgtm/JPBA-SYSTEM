@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">公認番号の自動採番設定</h2>
            <p class="text-muted mb-0">時間差で入力しても、番号は記録編集画面から後で修正できます。</p>
        </div>
        <a href="{{ route('record_types.index') }}" class="btn btn-outline-secondary">公認記録へ戻る</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr><th>種別</th><th>区分</th><th>次に使う番号</th><th>接頭辞</th><th>接尾辞</th><th>自動採番</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ([
                    'perfect' => '公認パーフェクト',
                    'eight_hundred' => '公認800シリーズ',
                    'seven_ten' => '公認7－10メイド',
                ] as $type => $typeLabel)
                    @foreach (['M' => '男子', 'F' => '女子'] as $gender => $genderLabel)
                        @php($sequence = $sequences->get($type.':'.$gender))
                        <tr>
                            <form method="POST" action="{{ route('record_types.sequences.store') }}">
                                @csrf
                                <input type="hidden" name="record_type" value="{{ $type }}">
                                <input type="hidden" name="gender" value="{{ $gender }}">
                                <td>{{ $typeLabel }}</td>
                                <td>{{ $genderLabel }}</td>
                                <td><input type="number" name="next_number" min="1" required class="form-control" value="{{ $sequence?->next_number ?? 1 }}"></td>
                                <td><input type="text" name="prefix" class="form-control" value="{{ $sequence?->prefix }}"></td>
                                <td><input type="text" name="suffix" class="form-control" value="{{ $sequence?->suffix }}"></td>
                                <td class="text-center">
                                    <input type="hidden" name="is_enabled" value="0">
                                    <input type="checkbox" name="is_enabled" value="1" @checked($sequence?->is_enabled ?? true)>
                                </td>
                                <td><button type="submit" class="btn btn-primary btn-sm">保存</button></td>
                            </form>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
