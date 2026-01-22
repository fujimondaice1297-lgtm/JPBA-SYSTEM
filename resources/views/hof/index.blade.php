@extends('layouts.app')
@section('title', '殿堂入り（確認用）')

@section('content')
<div class="container py-3">
  <h1 class="h3 mb-3">日本プロボウリング殿堂</h1>

  <a href="{{ route('hof.create') }}" class="btn btn-sm btn-outline-primary mb-3">殿堂レコードを作成</a>

  <div class="mb-3">
    @foreach($years as $y)
      <a class="btn btn-outline-secondary btn-sm me-1 mb-1" href="#y{{ $y }}">{{ $y }}年度</a>
    @endforeach
  </div>

  @foreach($years as $y)
    <section id="y{{ $y }}" class="mb-4">
      <h2 class="h5 mb-3">【{{ $y }}年度表彰】</h2>
      <div class="row g-3">
        @foreach(($byYear[$y] ?? []) as $p)
          <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <a class="text-decoration-none" href="{{ route('hof.show', ['slug' => $p['slug']]) }}">
              <div class="card h-100 shadow-sm">
                <div class="ratio ratio-4x3 bg-light">
                  <img src="{{ $p['portrait_url'] ?? asset('images/placeholder-portrait.jpg') }}"
                       alt="{{ $p['name'] }}" class="img-fluid w-100 h-100 object-fit-cover">
                </div>
                <div class="card-body py-2">
                  <div class="small fw-bold text-dark">{{ $p['name'] }}</div>
                </div>
              </div>
            </a>
          </div>
        @endforeach
      </div>
    </section>
  @endforeach
</div>
@endsection
