@extends('layouts.app')

@section('content')
@php
  $returnUrl = request()->fullUrl();
@endphp

<div class="container py-3">
  <h2 class="mb-2">{{ $page['title'] ?? '資格者一覧' }}</h2>

  @if(!empty($page['conditions']))
    <div class="alert alert-secondary small">
      <div class="fw-bold mb-1">【取得条件】</div>
      <ul class="mb-0">
        @foreach($page['conditions'] as $line)
          <li>{{ $line }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="mb-2 text-muted">該当人数：{{ number_format(count($rows)) }} 名</div>

  @if(empty($rows))
    <div class="text-muted">該当する選手がいません。</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:56px;">写真</th>
            <th>氏名</th>
            <th>ライセンス</th>
            @if(($page['code'] ?? '') === 'evergreen')
              <th>永久シード取得</th>
            @else
              <th>A級番号</th>
            @endif
          </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
          @php
            // 表示用ライセンス（英字プレフィックス除去）
            $licenseDigits = $r['license_no'] ? preg_replace('/^[A-Za-z]+/', '', $r['license_no']) : '';
            // 公開プロフィールへ
            $profileUrl = route('pro_bowlers.public_show', $r['id']) . '?return=' . urlencode($returnUrl);
          @endphp
          <tr>
            <td>
              @if($r['portrait_url'])
                <img src="{{ $r['portrait_url'] }}" alt="" class="rounded-circle" style="width:44px;height:44px;object-fit:cover;">
              @endif
            </td>
            <td>
              <a href="{{ $profileUrl }}" class="link-dark text-decoration-none fw-bold">
                {{ $r['name'] }}
              </a>
              @if(!empty($r['name_kana']))<div class="text-muted small">{{ $r['name_kana'] }}</div>@endif
            </td>
            <td><code>{{ $licenseDigits ?: '-' }}</code></td>

            @if(($page['code'] ?? '') === 'evergreen')
              <td>{{ $r['seed_date'] ? \Illuminate\Support\Str::of($r['seed_date'])->substr(0,10) : '' }}</td>
            @else
              <td>{{ $r['a_number'] }}</td>
            @endif
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
