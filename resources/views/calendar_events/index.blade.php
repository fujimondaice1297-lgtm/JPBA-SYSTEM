@extends('layouts.app')
@section('content')
<div class="container">
  <div class="d-flex justify-content-between mb-3">
    <h2>手入力イベント一覧</h2>
    <div class="d-flex gap-2">
      <a class="btn btn-primary" href="{{ route('calendar_events.create') }}">新規作成</a>
      <a class="btn btn-outline-secondary" href="{{ route('calendar_events.importForm') }}">CSV/TSVインポート</a>
    </div>
  </div>

  @if(session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif

  <table class="table table-striped align-middle">
    <thead><tr>
      <th>日程</th><th>タイトル</th><th>種別</th><th>会場</th><th style="width:140px;"></th>
    </tr></thead>
    <tbody>
      @foreach($events as $e)
        @php $sd=$e->start_date; $ed=$e->end_date?:$sd; $period=$sd->equalTo($ed)?$sd->format('Y/m/d'):$sd->format('Y/m/d').' - '.$ed->format('Y/m/d'); @endphp
        <tr>
          <td>{{ $period }}</td>
          <td>{{ $e->title }}</td>
          <td>{{ $e->kind==='pro_test'?'プロテスト':($e->kind==='approved'?'承認大会':'その他') }}</td>
          <td>{{ $e->venue }}</td>
          <td class="text-end">
            <a href="{{ route('calendar_events.edit',$e->id) }}" class="btn btn-sm btn-outline-primary">編集</a>
            @if(auth()->user()?->isAdmin())
              <form method="POST"
                    action="{{ route('admin.calendar_events.destroy',$e->id) }}"
                    class="d-inline"
                    onsubmit="return confirm('削除しますか？')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">削除</button>
              </form>
            @endif
          </td>

        </tr>
      @endforeach
    </tbody>
  </table>
  {{ $events->links() }}
</div>
@endsection
