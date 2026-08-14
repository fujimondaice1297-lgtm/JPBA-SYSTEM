@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">公認記録の確認・編集</h2>
        <a href="{{ route('record_types.show', $recordType) }}" class="btn btn-outline-secondary">詳細表示</a>
    </div>

    @include('record_types._form', [
        'action' => route('record_types.update', $recordType),
        'method' => 'PUT',
        'recordType' => $recordType,
    ])

    @if (auth()->user()?->isAdmin())
        <hr class="my-4">
        <form action="{{ route('admin.record_types.destroy', $recordType) }}" method="POST"
              onsubmit="return confirm('この明細を削除しますか？ 一度総数へ反映した記録は、明細を削除しても総数から減りません。');">
            @csrf
            @method('DELETE')
            @if(request('return_to') === 'pro_bowler_edit')
                <input type="hidden" name="return_to" value="pro_bowler_edit">
            @endif
            <button type="submit" class="btn btn-outline-danger">明細を削除</button>
        </form>
    @endif
</div>
@endsection
