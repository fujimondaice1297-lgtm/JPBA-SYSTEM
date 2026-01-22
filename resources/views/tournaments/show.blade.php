{{-- resources/views/tournaments/show.blade.php --}}
@extends('layouts.app')

@section('content')
@php
  use Carbon\Carbon;
  $w = ['日','月','火','水','木','金','土'];
  $fmt = fn($d) => $d ? ( ($d instanceof \DateTimeInterface ? Carbon::instance($d) : Carbon::parse($d))->format('n/j') . '(' . $w[($d instanceof \DateTimeInterface ? Carbon::instance($d) : Carbon::parse($d))->dayOfWeek] . ')' ) : null;
  $period = ($tournament->start_date && $tournament->end_date) ? $fmt($tournament->start_date) . ' ～ ' . $fmt($tournament->end_date) : '—';

  $orgLabels = [
    'host'             => '主催',
    'special_sponsor'  => '特別協賛',
    'sponsor'          => '協賛',
    'support'          => '後援',
    'cooperation'      => '協力',
  ];
  $orgByCat = collect($orgLabels)->mapWithKeys(function($label,$key) use($tournament){
      $items = $tournament->organizations
          ->where('category',$key)
          ->sortBy('sort_order')
          ->values();
      return [$key => $items];
  });

  $spectator = match($tournament->spectator_policy) {
      'paid' => '可（有料）',
      'free' => '可（無料）',
      'none' => '不可',
      default => '未設定',
  };

  /* ---- 右サイド：日程・成績（正規化）---- */
  $raw = collect($tournament->sidebar_schedule ?? [])->map(function($r){
    return [
      'date'      => trim((string)($r['date']  ?? '')),
      'label'     => trim((string)($r['label'] ?? ($r['title'] ?? ''))),
      'href'      => (string)($r['href']  ?? ($r['file'] ?? ($r['url'] ?? ''))),
      'separator' => (bool)($r['separator'] ?? false),
    ];
  })->filter(fn($x)=> $x['separator'] || $x['label']!=='' || $x['href']!=='' );

  $normalized = collect();
  $lastDate = null;
  foreach ($raw as $r) {
    $d = $r['date'] !== '' ? $r['date'] : ($lastDate ?? '__no_date__');
    if ($r['date'] !== '') $lastDate = $r['date'];
    $normalized->push([
      'date'      => $d,
      'label'     => $r['label'],
      'href'      => $r['href'],
      'separator' => $r['separator'],
    ]);
  }
  $grouped = $normalized->groupBy('date');
  $orderKeys = $normalized->pluck('date')->unique()->filter(fn($k)=>$k!=='__no_date__')->values();
  if ($grouped->has('__no_date__')) $orderKeys->push('__no_date__');

  // 褒章
  $awards = collect($tournament->award_highlights ?? []);
  $awardCats = [
    'perfect'   => 'パーフェクト',
    'series800' => '800シリーズ',
    'split710'  => '7-10メイド',
  ];

  // ★ 終了後：優勝者・トーナメント（カード）
  //   photos[] があれば優先。無ければ従来の photo を1枚として扱う（後方互換）。
  $resultCards = collect($tournament->result_cards ?? [])
    ->map(function($c){
      $photos = [];
      if (!empty($c['photos']) && is_array($c['photos'])) {
        foreach ($c['photos'] as $p) { if ($p) $photos[] = $p; }
      } elseif (!empty($c['photo'])) {
        $photos[] = $c['photo'];
      }
      return [
        'title'  => trim((string)($c['title']  ?? '')),
        'player' => trim((string)($c['player'] ?? '')),
        'balls'  => trim((string)($c['balls']  ?? '')),
        'note'   => trim((string)($c['note']   ?? '')),
        'url'    => (string)($c['url'] ?? ''),
        'photos' => $photos,
        'file'   => $c['file']  ?? null,
      ];
    })
    ->filter(fn($x)=>$x['title']!=='' || $x['player']!=='' || !empty($x['photos']) || $x['file'] || $x['url']!=='' || $x['balls']!=='' || $x['note']!=='')
    ->values();
@endphp

{{-- ヘッダ：ロゴ＋タイトル／操作ボタン --}}
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="d-flex align-items-center gap-2">
    @if(!empty($tournament->title_logo_path))
      <img src="{{ asset('storage/'.$tournament->title_logo_path) }}" alt="title logo" style="height:40px; width:auto; border-radius:6px;">
    @endif
    <h1 class="h3 mb-0">{{ $tournament->name }}</h1>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-primary">編集</a>
    <a href="{{ route('tournaments.index') }}" class="btn btn-secondary">一覧へ戻る</a>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
  <div class="col-lg-8">
    {{-- トップ画像 --}}
    @if(!empty($tournament->hero_image_path))
      <div class="mb-3">
        <img src="{{ asset('storage/'.$tournament->hero_image_path) }}" alt=""
             style="width:100%; max-height:240px; object-fit:cover; border-radius:10px;">
      </div>
    @endif

    <table class="table table-bordered">
      <tbody>
        <tr><th style="width:180px;">開催期間（表示用）</th><td>{{ $period }}</td></tr>
        <tr><th>種別</th><td>{{ $tournament->officialTypeLabel }} / {{ $tournament->genderLabel }}</td></tr>
        <tr><th>大会観覧</th><td>{{ $spectator }}</td></tr>
        <tr><th>会場名</th><td>{{ $tournament->venue_name ?: '—' }}</td></tr>
        <tr><th>会場住所</th><td>{{ $tournament->venue_address ?: '—' }}</td></tr>
        <tr><th>電話番号</th><td>{{ $tournament->venue_tel ?: '—' }}</td></tr>
        <tr><th>FAX</th><td>{{ $tournament->venue_fax ?: '—' }}</td></tr>
        <tr>
          <th>会場サイト</th>
          <td>
            @if(!empty($tournament->venue?->website_url))
              <a href="{{ $tournament->venue->website_url }}" target="_blank" rel="noopener">{{ $tournament->venue->website_url }}</a>
            @else
              —
            @endif
          </td>
        </tr>

        {{-- 主催/協賛等（新→旧フォールバック） --}}
        @foreach($orgLabels as $cat => $label)
          @php
            $items = $orgByCat[$cat] ?? collect();
            $fallbackText = $tournament->{$cat} ?? null;
          @endphp
          @if(($items && $items->count()) || $fallbackText)
            <tr>
              <th>{{ $label }}</th>
              <td>
                @if($items && $items->count())
                  @foreach($items as $i)
                    @if($i->url)
                      <div><a href="{{ $i->url }}" target="_blank" rel="noopener">{{ $i->name }}</a></div>
                    @else
                      <div>{{ $i->name }}</div>
                    @endif
                  @endforeach
                @else
                  {{ $fallbackText }}
                @endif
              </td>
            </tr>
          @endif
        @endforeach

        <tr>
          <th>TV放映</th>
          <td>
            {{ $tournament->broadcast ?: '—' }}
            @if(!empty($tournament->broadcast_url))
              &nbsp;<a href="{{ $tournament->broadcast_url }}" target="_blank" rel="noopener">放映サイト</a>
            @endif
          </td>
        </tr>
        <tr>
          <th>配信</th>
          <td>
            {{ $tournament->streaming ?: '—' }}
            @if(!empty($tournament->streaming_url))
              &nbsp;<a href="{{ $tournament->streaming_url }}" target="_blank" rel="noopener">配信ページ</a>
            @endif
          </td>
        </tr>
        <tr><th>賞金</th><td>{!! nl2br(e($tournament->prize ?? '—')) !!}</td></tr>
        <tr><th>入場料</th><td>{!! nl2br(e($tournament->admission_fee ?? '—')) !!}</td></tr>
        <tr><th>出場条件</th><td>{!! nl2br(e($tournament->entry_conditions ?? '—')) !!}</td></tr>
        <tr>
          <th>資料メモ</th>
          <td>{!! nl2br(e($tournament->materials ?? '—')) !!}</td>
        </tr>
        <tr>
          <th>前年大会</th>
          <td>
            {{ $tournament->previous_event ?: '—' }}
            @if(!empty($tournament->previous_event_url))
              &nbsp;<a href="{{ $tournament->previous_event_url }}" target="_blank" rel="noopener">前年大会ページ</a>
            @endif
          </td>
        </tr>

        @php
          $files = $tournament->files->sortBy(['type','sort_order']);
          $pdf = fn($type) => $files->firstWhere('type',$type);
        @endphp
        <tr>
          <th>資料（PDF）</th>
          <td>
            @if($pdf('outline_public'))
              <div><a href="{{ asset('storage/'.$pdf('outline_public')->file_path) }}" target="_blank" rel="noopener">大会要項（一般）</a></div>
            @endif
            @if($pdf('outline_player'))
              <div><a href="{{ asset('storage/'.$pdf('outline_player')->file_path) }}" target="_blank" rel="noopener">大会要項（選手）</a></div>
            @endif
            @if($pdf('oil_pattern'))
              <div><a href="{{ asset('storage/'.$pdf('oil_pattern')->file_path) }}" target="_blank" rel="noopener">オイルパターン表</a></div>
            @endif
            @foreach($files->where('type','custom') as $cf)
              <div><a href="{{ asset('storage/'.$cf->file_path) }}" target="_blank" rel="noopener">{{ $cf->title ?: '資料' }}</a></div>
            @endforeach
            @if(!$files->count())
              —
            @endif
          </td>
        </tr>

        @if(!empty($tournament->image_path) || !empty($tournament->poster_images))
          <tr>
            <th>ポスター</th>
            <td class="d-flex flex-wrap gap-3">
              @if(!empty($tournament->image_path))
                <img src="{{ asset('storage/'.$tournament->image_path) }}" alt="" style="max-height:160px;">
              @endif
              @if(is_array($tournament->poster_images))
                @foreach($tournament->poster_images as $pi)
                  <img src="{{ asset('storage/'.$pi) }}" alt="" style="max-height:160px;">
                @endforeach
              @endif
            </td>
          </tr>
        @endif
      </tbody>
    </table>

    {{-- ★ 決勝・優勝ハイライト（カード） --}}
    @if($resultCards->count())
      <div class="card mb-3">
        <div class="card-header fw-bold">決勝・優勝ハイライト</div>
        <div class="card-body">
          <div class="row g-3">
            @foreach($resultCards as $c)
              @php
                $pdf = $c['file'] ? asset('storage/'.$c['file']) : null;
                $imgs = collect($c['photos'] ?? [])->map(fn($p)=> asset('storage/'.$p));
                $ext  = $c['url'] && str_starts_with($c['url'],'http') ? $c['url'] : null;
              @endphp
              <div class="col-12">
                <div class="d-flex gap-3 align-items-start border rounded p-2">
                  @if($imgs->count())
                    <div class="rc-photos">
                      @foreach($imgs as $u)
                        <img src="{{ $u }}" alt="" class="rc-photo">
                      @endforeach
                    </div>
                  @endif
                  <div class="flex-fill">
                    @if($c['title']) <div class="fw-bold">{{ $c['title'] }}</div> @endif
                    @if($c['player'])<div class="fs-5">{{ $c['player'] }}</div>@endif
                    @if($c['balls']) <div class="text-muted" style="white-space:pre-line;">{{ $c['balls'] }}</div> @endif
                    @if($c['note'])  <div class="text-muted">{{ $c['note'] }}</div> @endif
                    <div class="mt-1 d-flex gap-3">
                      @if($pdf)<a href="{{ $pdf }}" target="_blank" rel="noopener">トーナメント表（PDF）</a>@endif
                      @if($ext)<a href="{{ $ext }}" target="_blank" rel="noopener">関連リンク</a>@endif
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endif
  </div>

  {{-- 右サイド --}}
  <div class="col-lg-4">

    {{-- 日程・成績（左＝日付 / 右＝内容。区切り線は破線） --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">日程・成績</div>

      <div class="p-2">
        @if($orderKeys->count())
          @foreach($orderKeys as $dateKey)
            @php $items = $grouped->get($dateKey) ?? collect(); @endphp
            <div class="schedule-block border rounded mb-2">
              <div class="schedule-grid">
                <div class="schedule-date">
                  {{ $dateKey==='__no_date__' ? '日付未設定' : $dateKey }}
                </div>
                <div class="schedule-items">
                  @foreach($items as $r)
                    @php
                      $href = $r['href'] ? (str_starts_with($r['href'],'http') ? $r['href'] : asset('storage/'.$r['href'])) : null;
                    @endphp
                    @if(!empty($r['separator']))
                      <hr class="schedule-sep">
                    @else
                      <div class="schedule-line">
                        @if($href)
                          <a href="{{ $href }}" target="_blank" rel="noopener">{{ $r['label'] ?: '（無題）' }}</a>
                        @else
                          {{ $r['label'] ?: '（無題）' }}
                        @endif
                      </div>
                    @endif
                  @endforeach
                </div>
              </div>
            </div>
          @endforeach
        @else
          <div class="text-muted px-2 py-3">未登録</div>
        @endif
      </div>
    </div>

    {{-- 褒章達成 --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">褒章達成</div>
      <div class="card-body">
        @foreach($awardCats as $key => $label)
          @php $list = $awards
              ->map(function($a){
                return [
                  'type'   => $a['type']   ?? ($a['category'] ?? 'perfect'),
                  'player' => trim((string)($a['player'] ?? '')),
                  'game'   => trim((string)($a['game'] ?? '')),
                  'lane'   => trim((string)($a['lane'] ?? '')),
                  'note'   => trim((string)($a['note'] ?? '')),
                  'title'  => trim((string)($a['title'] ?? '')),
                  'photo'  => $a['photo']  ?? null,
                ];
              })
              ->where('type',$key)
              ->filter(fn($x)=>($x['player']!=='' || $x['title']!=='' || $x['photo']))
              ->values();
          @endphp
          <div class="mb-3">
            <div class="fw-bold">{{ $label }}</div>
            @if($list->count())
              @foreach($list as $a)
                <div class="d-flex gap-2 align-items-start border rounded p-2 mb-2">
                  @if(!empty($a['photo']))
                    <img src="{{ asset('storage/'.$a['photo']) }}" style="width:92px; height:92px; object-fit:cover; border-radius:6px;">
                  @endif
                  <div class="small">
                    @if(!empty($a['title']))<div class="text-muted">{{ $a['title'] }}</div>@endif
                    @if(!empty($a['player']))<div class="fw-bold">{{ $a['player'] }}</div>@endif
                    @if(!empty($a['game']) || !empty($a['lane']))
                      <div class="text-muted">
                        {{ $a['game'] ?? '' }}@if(!empty($a['game']) && !empty($a['lane'])) ／ @endif{{ $a['lane'] ?? '' }}
                      </div>
                    @endif
                    @if(!empty($a['note']))<div class="text-muted">{{ $a['note'] }}</div>@endif
                  </div>
                </div>
              @endforeach
            @else
              <div class="text-muted">該当なし</div>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>

{{-- このビュー専用の軽量スタイル --}}
<style>
  /* === 日程・成績の2カラム（左：日付／右：内容） === */
  .schedule-block { background:#fff; }
  .schedule-grid {
    display:grid;
    grid-template-columns: 92px 1fr;
    gap:10px;
    align-items: stretch;
    padding:10px;
  }
  .schedule-date{
    display:flex;
    align-items:flex-start;
    justify-content:center;
    color:#495057;
    font-weight:600;
    font-size:.95rem;
    text-align:center;
    border-right:1px solid #dee2e6;
    padding-right:10px;
    white-space:nowrap;
  }
  .schedule-items{ padding-top:2px; }
  .schedule-line + .schedule-line{ margin-top:.35rem; }
  .schedule-sep{
    border:0;
    border-top:1px dashed #9aa0a6;
    margin:.5rem 0;
  }
  .schedule-block{ border-color:#d5d9df !important; }

  /* === 決勝・優勝ハイライト：写真複数を同枠で横並び === */
  .rc-photos{ display:flex; gap:8px; flex-wrap:wrap; }
  .rc-photo{
    width:180px; height:120px; object-fit:cover;
    border-radius:8px; border:1px solid #eee; background:#fff;
  }
</style>
@endsection
