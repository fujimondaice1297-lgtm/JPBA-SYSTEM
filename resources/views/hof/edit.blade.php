@extends('layouts.app')
@section('title','殿堂レコード編集')

@section('content')
<div class="container py-3">
  @php
    $user = auth()->user();
    // 役割判定：hasRole('admin') があればそれを使う／無ければ $user->role === 'admin'
    $isAdmin = $user && (
        (method_exists($user,'hasRole') && $user->hasRole('admin'))
        || (($user->role ?? null) === 'admin')
    );
  @endphp

  @if (session('ok'))   <div class="alert alert-success">{{ session('ok') }}</div>@endif
  @if (session('info')) <div class="alert alert-info">{{ session('info') }}</div>@endif
  @if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">
      @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul></div>
  @endif

  <div class="row g-4">
    <div class="col-12 col-md-4">
      <div class="card">
        <img src="{{ $pro?->portrait_url ?? asset('images/placeholder-portrait.jpg') }}" class="card-img-top" alt="{{ $pro?->name ?? 'portrait' }}">
        <div class="card-body">
          <h2 class="h5 mb-1">{{ $pro?->name ?? '（不明）' }}</h2>
          <div class="small text-muted">slug: {{ $pro?->slug ?? '—' }}</div>
          <a class="small d-inline-block mt-2" href="{{ route('hof.index') }}">&larr; 殿堂一覧に戻る</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-8">
      <div class="card mb-4">
        <div class="card-header fw-bold">殿堂情報</div>
        <div class="card-body">
          <form method="post" action="{{ route('hof.update',['id'=>$hof->id]) }}">
            @csrf @method('PUT')
            <div class="mb-3">
              <label class="form-label">殿堂入り年</label>
              <input type="number" name="year" class="form-control" min="1900" max="2100" value="{{ old('year',$hof->year) }}">
            </div>
            <div class="mb-3">
              <label class="form-label">顕彰文</label>
              <textarea name="citation" rows="4" class="form-control">{{ old('citation',$hof->citation) }}</textarea>
            </div>
            <button class="btn btn-primary">保存</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header fw-bold">写真（管理者は削除可）</div>
        <div class="card-body">
          @if(count($photos))
            <div class="row g-2 mb-3">
              @foreach($photos as $ph)
                <div class="col-6 col-lg-4">
                  <div class="ratio ratio-4x3 bg-light">
                    <img src="{{ $ph->url }}" class="img-fluid w-100 h-100 object-fit-cover" alt="">
                  </div>
                  <div class="small mt-1 text-muted">{{ $ph->credit }}</div>
                  <div class="small">並び順値: {{ $ph->sort_order }}</div>

                  @if($isAdmin)
                  <form method="post"
                        action="{{ route('admin.hof.photos.destroy',['photo'=>$ph->id]) }}"
                        class="mt-1"
                        onsubmit="return confirm('この写真を削除します。よろしいですか？');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">削除（管理者のみ）</button>
                  </form>
                  @endif
                </div>
              @endforeach
            </div>
          @endif

          {{-- アップロード一本化 --}}
          <form method="post" action="{{ route('hof.photos.upload',['id'=>$hof->id]) }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-6">
              <label class="form-label">写真ファイル</label>
              <input type="file" name="file" class="form-control" required>
              <div class="form-text">初回は <code>/_dev/storage/link</code> を一度踏む。</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">表示位置</label>
              <select name="position" class="form-select">
                <option value="asc">昇順（先頭に入れる）</option>
                <option value="desc" selected>降順（末尾に入れる）</option>
              </select>
              <div class="form-text">ギャラリーは <code>sort_order</code> 昇順で表示。</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">クレジット（任意）</label>
              <input type="text" name="credit" class="form-control" placeholder="撮影者など">
            </div>
            <div class="col-12">
              <button class="btn btn-outline-primary">アップロードして追加</button>
            </div>
          </form>

          @if($isAdmin)
          {{-- 危険ゾーン：殿堂レコードの削除（管理者のみ） --}}
          <div class="card border-danger mt-4">
            <div class="card-header fw-bold text-danger">殿堂レコードの削除（管理者のみ）</div>
            <div class="card-body">
              <p class="mb-2">この操作は取り消せません。写真（アップロード分の実体ファイルを含む）も合わせて削除されます。</p>
              <form method="post" action="{{ route('admin.hof.destroy', ['id'=>$hof->id]) }}"
                    onsubmit="return confirm('殿堂レコードを完全に削除します。よろしいですか？');">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger">殿堂レコードを削除する</button>
              </form>
            </div>
          </div>
          @endif

        </div>
      </div>

    </div>
  </div>
</div>
@endsection
