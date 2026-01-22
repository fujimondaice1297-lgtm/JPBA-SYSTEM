@extends('layouts.app')

@section('content')
  <h1>送信履歴：{{ $group->name }}</h1>

  <div class="mb-3">
    <div><strong>件名：</strong>{{ $mailout->subject }}</div>
    <div><strong>状態：</strong>{{ $mailout->status }}　
      <span class="text-muted">成功 {{ $recap['sent'] }} / 失敗 {{ $recap['failed'] }}</span>
    </div>
    <div class="mt-2"><strong>本文プレビュー：</strong></div>
    <div class="border rounded p-3">{!! nl2br(e($mailout->body)) !!}</div>
  </div>

  <div class="card">
    <div class="card-header fw-bold">直近の送信状況（最大50件）</div>
    <div class="card-body p-0">
      <table class="table mb-0 table-striped">
        <thead><tr><th>ライセンス</th><th>氏名</th><th>宛先</th><th>結果</th><th>時刻</th></tr></thead>
        <tbody>
          @forelse($samples as $r)
            <tr>
              <td>{{ $r->bowler?->license_no }}</td>
              <td>{{ $r->bowler?->name_kanji }}</td>
              <td>{{ $r->email }}</td>
              <td>{{ $r->status }} @if($r->error_message) <span class="text-danger">({{ $r->error_message }})</span>@endif</td>
              <td>{{ optional($r->sent_at)->format('Y-m-d H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">データがありません。</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <a class="btn btn-outline-secondary" href="{{ route('pro_groups.show',$group) }}">グループ詳細へ戻る</a>
  </div>
@endsection
