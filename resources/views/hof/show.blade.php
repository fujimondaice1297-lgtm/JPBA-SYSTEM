@extends('layouts.app')
@section('title', ($vm['pro']['name'] ?? '殿堂') . ' | 日本プロボウリング殿堂')

@section('content')
<div class="container py-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('hof.index') }}" class="small">&larr; 殿堂一覧に戻る</a>

    @auth
      @if (auth()->user()->isAdmin() && isset($vm['induction']['id']))
        <a class="btn btn-sm btn-outline-primary"
           href="{{ route('hof.edit', ['id'=>$vm['induction']['id']]) }}">
          写真を追加 / 殿堂情報編集
        </a>
      @endif
    @endauth
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-4">
      <div class="card">
        <img src="{{ $vm['pro']['portrait_url'] ?? asset('images/placeholder-portrait.jpg') }}"
             class="card-img-top" alt="">
        <div class="card-body">
          <h2 class="h5 mb-1">{{ $vm['pro']['name'] ?? '（不明）' }}</h2>
          <div class="small text-muted">slug: {{ $vm['pro']['slug'] ?? '—' }}</div>

          <div class="mt-3">
            <span class="badge bg-secondary">殿堂入り {{ $vm['induction']['year'] ?? '—' }}</span>
          </div>

          @if(!empty($vm['facts']))
            <hr>
            <table class="table table-sm mb-0">
              <tbody>
                @foreach ($vm['facts'] as $f)
                  <tr>
                    <th class="text-nowrap" style="width:8rem">{{ $f['label'] }}</th>
                    <td>{{ $f['value'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      @if(!empty($vm['induction']['citation']))
        <div class="card mb-4">
          <div class="card-header fw-bold">顕彰文</div>
          <div class="card-body">
            <p class="mb-0">{{ $vm['induction']['citation'] }}</p>
          </div>
        </div>
      @endif

      @if(!empty($vm['induction']['photos']))
        <div class="card mb-4">
          <div class="card-header fw-bold">フォトギャラリー</div>
          <div class="card-body">
            <div class="row g-2">
              @foreach($vm['induction']['photos'] as $p)
                <div class="col-6 col-md-4">
                  <div class="ratio ratio-4x3 bg-light">
                    <img src="{{ $p['url'] }}" class="img-fluid w-100 h-100 object-fit-cover" alt="">
                  </div>
                  @if(!empty($p['credit']))
                    <div class="small text-muted mt-1">{{ $p['credit'] }}</div>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @endif

      <div class="card">
        <div class="card-header fw-bold">Main Title</div>
        <ul class="list-group list-group-flush">
          @forelse($vm['titles'] ?? [] as $t)
            <li class="list-group-item d-flex justify-content-between">
              <span>{{ $t['year'] }}　{{ $t['name'] }}</span>
              @if(!empty($t['note']))<span class="text-muted small">{{ $t['note'] }}</span>@endif
            </li>
          @empty
            <li class="list-group-item text-muted">タイトル情報はありません。</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
