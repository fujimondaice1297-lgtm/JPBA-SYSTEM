<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="format-detection" content="telephone=no">
  <title>公益社団法人 日本プロボウリング協会 JPBA</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --jpba-blue: #174a8b;
      --jpba-red: #c5282f;
      --jpba-ink: #1f2933;
      --jpba-line: #d8dee8;
      --jpba-soft: #f5f7fa;
      --jpba-gold: #b78b20;
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

    .jpba-content {
      padding: 22px;
    }

    .jpba-section-title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 0 0 12px;
      color: var(--jpba-blue);
      font-size: 1.1rem;
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

    .jpba-tournament-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
    }

    .jpba-tournament {
      border: 1px solid var(--jpba-line);
      border-radius: 6px;
      overflow: hidden;
      background: #fff;
      min-width: 0;
    }

    .jpba-tournament-image {
      aspect-ratio: 16 / 9;
      background: var(--jpba-soft);
      border-bottom: 1px solid var(--jpba-line);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .jpba-tournament-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .jpba-tournament-image.logo-fallback img {
      width: 72%;
      height: 72%;
      object-fit: contain;
      opacity: .9;
    }

    .jpba-tournament-body {
      padding: 10px 11px 12px;
    }

    .jpba-date {
      color: var(--jpba-red);
      font-weight: 700;
      font-size: .88rem;
      margin-bottom: 5px;
    }

    .jpba-tournament-name {
      min-height: 3.4em;
      margin: 0;
      font-weight: 700;
      font-size: .93rem;
      line-height: 1.45;
      overflow-wrap: anywhere;
    }

    .jpba-mini-links {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 10px;
    }

    .jpba-mini-links a,
    .jpba-pill {
      display: inline-flex;
      align-items: center;
      min-height: 26px;
      padding: 2px 8px;
      border-radius: 4px;
      background: #edf3fb;
      color: var(--jpba-blue);
      text-decoration: none;
      font-size: .78rem;
      font-weight: 700;
    }

    .jpba-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 300px;
      gap: 22px;
      margin-top: 22px;
    }

    .jpba-list {
      border: 1px solid var(--jpba-line);
      border-radius: 6px;
      overflow: hidden;
    }

    .jpba-info-row {
      display: grid;
      grid-template-columns: 120px 92px minmax(0, 1fr);
      gap: 10px;
      padding: 10px 12px;
      border-bottom: 1px solid var(--jpba-line);
      align-items: start;
    }

    .jpba-info-row:last-child { border-bottom: 0; }
    .jpba-info-row a { font-weight: 700; text-decoration: none; overflow-wrap: anywhere; }

    .jpba-category {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 70px;
      min-height: 24px;
      padding: 2px 7px;
      background: #f1f5f9;
      border: 1px solid #dbe3ef;
      border-radius: 4px;
      color: #384759;
      font-size: .78rem;
      font-weight: 700;
    }

    .jpba-side-block {
      border: 1px solid var(--jpba-line);
      border-radius: 6px;
      padding: 12px;
      background: #fff;
      margin-bottom: 14px;
    }

    .jpba-side-title {
      margin: 0 0 10px;
      color: var(--jpba-blue);
      font-size: .98rem;
      font-weight: 700;
    }

    .jpba-link-list {
      display: grid;
      gap: 8px;
    }

    .jpba-link-list a {
      display: block;
      padding: 8px 10px;
      border: 1px solid var(--jpba-line);
      border-radius: 4px;
      background: var(--jpba-soft);
      text-decoration: none;
      font-weight: 700;
      overflow-wrap: anywhere;
    }

    .jpba-empty {
      padding: 16px;
      border: 1px solid var(--jpba-line);
      border-radius: 6px;
      color: #697586;
      background: var(--jpba-soft);
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
      .jpba-tournament-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .jpba-layout { grid-template-columns: 1fr; }
      .jpba-info-row { grid-template-columns: 1fr; gap: 4px; }
    }

    @media (max-width: 560px) {
      .jpba-page { border: 0; }
      .jpba-head, .jpba-content, .jpba-footer { padding-left: 14px; padding-right: 14px; }
      .jpba-logo { align-items: flex-start; }
      .jpba-logo img { width: 84px; }
      .jpba-title { font-size: 1.15rem; }
      .jpba-utility { justify-content: flex-start; }
      .jpba-tournament-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
@php
  $config = $publicConfig ?? [];
  $navLinks = $config['primary_nav'] ?? [];
  $utilityLinks = $config['utility_links'] ?? [];
  $featuredPdfLinks = $config['featured_pdf_links'] ?? [];
  $channelLinks = $config['channel_links'] ?? [];
  $footerLinks = $config['footer_links'] ?? [];
  $logoUrl = asset('images/jpba_logo.png');

  $urlFor = function (array $link) {
      if (!empty($link['route']) && \Illuminate\Support\Facades\Route::has($link['route'])) {
          return route($link['route'], $link['params'] ?? []);
      }

      return $link['url'] ?? '#';
  };

  $formatPeriod = function ($start, $end) {
      if (!$start && !$end) {
          return '';
      }

      $s = $start ? \Carbon\Carbon::parse($start)->format('Y/n/j') : '';
      $e = $end ? \Carbon\Carbon::parse($end)->format('Y/n/j') : '';

      if ($s !== '' && $e !== '' && $s !== $e) {
          return "{$s}-{$e}";
      }

      return $s ?: $e;
  };

  $tournamentImage = function ($tournament) use ($logoUrl) {
      $paths = [
          $tournament->hero_image_path ?? null,
          $tournament->image_path ?? null,
          $tournament->title_logo_path ?? null,
      ];

      if (is_array($tournament->poster_images ?? null)) {
          foreach ($tournament->poster_images as $poster) {
              $paths[] = $poster;
          }
      }

      foreach ($paths as $path) {
          $path = trim((string) $path);
          if ($path !== '') {
              return ['url' => asset('storage/' . ltrim($path, '/')), 'fallback' => false];
          }
      }

      return ['url' => $logoUrl, 'fallback' => true];
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
    <section aria-labelledby="tournament-heading">
      <h2 id="tournament-heading" class="jpba-section-title">TOURNAMENT</h2>

      @if($tournaments->count())
        <div class="jpba-tournament-grid">
          @foreach($tournaments as $tournament)
            @php
              $image = $tournamentImage($tournament);
              $period = $formatPeriod($tournament->start_date, $tournament->end_date);
              $publicFiles = $tournament->files ?? collect();
            @endphp
            <article class="jpba-tournament">
              <div class="jpba-tournament-image {{ $image['fallback'] ? 'logo-fallback' : '' }}">
                <img src="{{ $image['url'] }}" alt="{{ $tournament->name }}">
              </div>
              <div class="jpba-tournament-body">
                @if($period !== '')
                  <div class="jpba-date">{{ $period }}</div>
                @endif
                <h3 class="jpba-tournament-name">{{ $tournament->name }}</h3>
                <div class="jpba-mini-links">
                  @foreach($publicFiles->take(3) as $file)
                    <a href="{{ asset('storage/' . ltrim((string) $file->file_path, '/')) }}" target="_blank" rel="noopener">
                      {{ $file->title ?: 'PDF' }}
                    </a>
                  @endforeach
                  @if(!empty($tournament->broadcast_url))
                    <a href="{{ $tournament->broadcast_url }}" target="_blank" rel="noopener">放映</a>
                  @endif
                  @if(!empty($tournament->streaming_url))
                    <a href="{{ $tournament->streaming_url }}" target="_blank" rel="noopener">配信</a>
                  @endif
                </div>
              </div>
            </article>
          @endforeach
        </div>
      @else
        <div class="jpba-empty">公開できる大会情報はまだ登録されていません。</div>
      @endif
    </section>

    <div class="jpba-layout">
      <section aria-labelledby="information-heading">
        <h2 id="information-heading" class="jpba-section-title">INFORMATION</h2>

        @if($informations->count())
          <div class="jpba-list">
            @foreach($informations as $info)
              <div class="jpba-info-row">
                <time datetime="{{ optional($info->updated_at)->format('Y-m-d') }}">
                  {{ optional($info->updated_at)->format('Y年n月j日') }}
                </time>
                <span class="jpba-category">{{ $info->category ?: 'NEWS' }}</span>
                <a href="{{ route('informations.show', $info) }}">{{ $info->title }}</a>
              </div>
            @endforeach
          </div>
          <div class="mt-3">
            <a href="{{ route('informations.index') }}">INFORMATION一覧へ</a>
          </div>
        @else
          <div class="jpba-empty">現在、一般公開のINFORMATIONはありません。</div>
        @endif
      </section>

      <aside aria-label="関連リンク">
        @if(!empty($featuredPdfLinks))
          <div class="jpba-side-block">
            <h2 class="jpba-side-title">公式PDF</h2>
            <div class="jpba-link-list">
              @foreach($featuredPdfLinks as $link)
                <a href="{{ $urlFor($link) }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
              @endforeach
            </div>
          </div>
        @endif

        @if(!empty($channelLinks))
          <div class="jpba-side-block">
            <h2 class="jpba-side-title">関連チャンネル</h2>
            <div class="jpba-link-list">
              @foreach($channelLinks as $link)
                <a href="{{ $urlFor($link) }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
              @endforeach
            </div>
          </div>
        @endif

        <div class="jpba-side-block">
          <h2 class="jpba-side-title">会員・関係者</h2>
          <div class="jpba-link-list">
            <a href="{{ route('login') }}">プロボウラー専用ページ</a>
            <a href="{{ route('informations.index') }}">一般公開INFORMATION</a>
          </div>
        </div>
      </aside>
    </div>
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
</body>
</html>
