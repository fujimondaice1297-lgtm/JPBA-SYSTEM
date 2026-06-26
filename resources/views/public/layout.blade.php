<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no">
  <title>@yield('title', '公益社団法人 日本プロボウリング協会 JPBA')</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --jpba-blue: #174a8b;
      --jpba-red: #c5282f;
      --jpba-ink: #1f2933;
      --jpba-line: #d8dee8;
      --jpba-soft: #f5f7fa;
    }

    body {
      margin: 0;
      color: var(--jpba-ink);
      background: #e8edf4;
      font-family: "Meiryo", "Yu Gothic", "Hiragino Kaku Gothic ProN", Arial, sans-serif;
      font-size: 14px;
      line-height: 1.65;
    }

    a { color: var(--jpba-blue); }
    a:hover { color: var(--jpba-red); }

    .jpba-page {
      max-width: 1080px;
      margin: 0 auto;
      background: #fff;
      min-height: 100vh;
      border-left: 1px solid var(--jpba-line);
      border-right: 1px solid var(--jpba-line);
    }

    .jpba-head {
      padding: 18px 22px 12px;
      border-top: 5px solid var(--jpba-blue);
      border-bottom: 1px solid var(--jpba-line);
    }

    .jpba-logo {
      display: flex;
      gap: 16px;
      align-items: center;
    }

    .jpba-logo img {
      width: 112px;
      height: auto;
      flex: 0 0 auto;
    }

    .jpba-title {
      margin: 0;
      color: var(--jpba-blue);
      font-size: 1.55rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .jpba-subtitle {
      color: #53606f;
      font-size: .82rem;
      letter-spacing: .02em;
    }

    .jpba-utility {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 16px;
      justify-content: flex-end;
      margin-top: 10px;
      font-size: .85rem;
    }

    .jpba-nav {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      border-bottom: 3px solid var(--jpba-blue);
      background: var(--jpba-blue);
    }

    .jpba-nav a {
      display: block;
      padding: 10px 8px;
      color: #fff;
      text-align: center;
      text-decoration: none;
      border-right: 1px solid rgba(255,255,255,.28);
      font-size: .9rem;
      font-weight: 700;
    }

    .jpba-nav a:last-child { border-right: 0; }
    .jpba-nav a:hover { background: #0f376c; color: #fff; }

    .jpba-content { padding: 22px; }

    .jpba-breadcrumb {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      color: #6b7788;
      font-size: .82rem;
      margin-bottom: 12px;
    }

    .jpba-page-title {
      margin: 0 0 18px;
      color: var(--jpba-blue);
      font-size: 1.35rem;
      font-weight: 700;
      border-bottom: 3px solid var(--jpba-blue);
      padding-bottom: 9px;
    }

    .jpba-section-title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 0 0 12px;
      color: var(--jpba-blue);
      font-size: 1.06rem;
      font-weight: 700;
      border-bottom: 2px solid var(--jpba-line);
      padding-bottom: 7px;
    }

    .jpba-section-title::before {
      content: "";
      width: 6px;
      height: 20px;
      background: var(--jpba-red);
      display: inline-block;
    }

    .jpba-panel {
      border: 1px solid var(--jpba-line);
      border-radius: 6px;
      background: #fff;
      padding: 14px;
      margin-bottom: 18px;
    }

    .jpba-data-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid var(--jpba-line);
    }

    .jpba-data-table th,
    .jpba-data-table td {
      border: 1px solid var(--jpba-line);
      padding: 9px 10px;
      vertical-align: top;
    }

    .jpba-data-table th {
      width: 180px;
      background: var(--jpba-soft);
      color: #35465a;
      font-weight: 700;
    }

    .jpba-link-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .jpba-link-grid a,
    .jpba-small-button {
      display: block;
      padding: 9px 10px;
      border: 1px solid var(--jpba-line);
      border-radius: 4px;
      background: var(--jpba-soft);
      text-decoration: none;
      font-weight: 700;
      overflow-wrap: anywhere;
    }

    .jpba-footer {
      margin-top: 24px;
      padding: 18px 22px 24px;
      border-top: 1px solid var(--jpba-line);
      color: #596675;
      font-size: .82rem;
      background: #f8fafc;
    }

    .jpba-footer-links {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 16px;
      margin-bottom: 10px;
    }

    @media (max-width: 900px) {
      .jpba-nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .jpba-link-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 560px) {
      .jpba-page { border: 0; }
      .jpba-head, .jpba-content, .jpba-footer { padding-left: 14px; padding-right: 14px; }
      .jpba-logo { align-items: flex-start; }
      .jpba-logo img { width: 84px; }
      .jpba-title { font-size: 1.15rem; }
      .jpba-utility { justify-content: flex-start; }
      .jpba-data-table th,
      .jpba-data-table td {
        display: block;
        width: 100%;
      }
    }
  </style>
  @stack('styles')
</head>
<body>
@php
  $config = $publicConfig ?? config('jpba_public', []);
  $navLinks = $config['primary_nav'] ?? [];
  $utilityLinks = $config['utility_links'] ?? [];
  $footerLinks = $config['footer_links'] ?? [];
  $logoUrl = asset('images/jpba_logo.png');

  $urlFor = function (array $link) {
      if (!empty($link['route']) && \Illuminate\Support\Facades\Route::has($link['route'])) {
          return route($link['route']);
      }

      return $link['url'] ?? '#';
  };
@endphp

<div class="jpba-page">
  <header class="jpba-head">
    <div class="jpba-logo">
      <a href="{{ route('public.home') }}" aria-label="JPBA HOME">
        <img src="{{ $logoUrl }}" alt="公益社団法人 日本プロボウリング協会 JPBA">
      </a>
      <div>
        <h1 class="jpba-title">公益社団法人 日本プロボウリング協会</h1>
        <div class="jpba-subtitle">Japan Professional Bowling Association</div>
      </div>
    </div>

    <div class="jpba-utility">
      @foreach($utilityLinks as $link)
        <a href="{{ $urlFor($link) }}">{{ $link['label'] }}</a>
      @endforeach
      <a href="{{ route('informations.index') }}">INFORMATION</a>
    </div>
  </header>

  <nav class="jpba-nav" aria-label="主要メニュー">
    @foreach($navLinks as $link)
      <a href="{{ $urlFor($link) }}">{{ $link['label'] }}</a>
    @endforeach
  </nav>

  <main class="jpba-content">
    <div class="jpba-breadcrumb">
      <a href="{{ route('public.home') }}">HOME</a>
      <span>&gt;</span>
      <span>@yield('breadcrumb', 'JPBA')</span>
    </div>

    @yield('content')
  </main>

  <footer class="jpba-footer">
    <div class="jpba-footer-links">
      @foreach($footerLinks as $link)
        <a href="{{ $urlFor($link) }}">{{ $link['label'] }}</a>
      @endforeach
    </div>
    <div>このサイトに掲載されている記事、写真、映像等、これらの素材をいかなる方法においても無断で複写・転載することは禁じられております。</div>
    <div class="mt-1">© 公益社団法人 日本プロボウリング協会, All Rights Reserved.</div>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
