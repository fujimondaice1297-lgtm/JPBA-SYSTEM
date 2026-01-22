@extends('layouts.app')

@section('content')
<div class="container py-3">
    <h1 class="h3 fw-bold mb-3">{{ $title }}</h1>

    <div class="bg-light border rounded p-3 mb-4">
        <div class="fw-semibold mb-1">取得条件</div>
        <ul class="mb-0 ps-4">
            @foreach($desc as $d)
                <li>{{ $d }}</li>
            @endforeach
        </ul>
    </div>

    @if(empty($list))
        <div class="text-muted">該当者がいません。</div>
    @else
        <div class="row g-3">
            @foreach($list as $p)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="d-flex align-items-center gap-3 border rounded p-2 bg-white">
                        @if($p['photo'])
                            <img src="{{ $p['photo'] }}" alt="" class="rounded-circle border" style="width:56px;height:56px;object-fit:cover;">
                        @else
                            <div class="rounded-circle border bg-secondary-subtle" style="width:56px;height:56px;"></div>
                        @endif
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $p['name'] }}</div>
                            @if($p['lic'])
                                <div class="text-muted small">Lic: {{ $p['lic'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
