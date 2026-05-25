{{-- resources/views/tournament_entries/lane_movement_table.blade.php --}}
@extends('layouts.app')

@section('content')
@php
  $settings = $laneMovement['settings'] ?? [];
  $rows = $laneMovement['rows'] ?? [];
  $games = (int)($settings['games'] ?? 0);
  $dayBlocks = $laneMovement['day_blocks'] ?? ($settings['day_blocks'] ?? []);
  if (!is_array($dayBlocks)) {
      $dayBlocks = [];
  }

  $formatTitleLines = function (?string $name): array {
      $name = trim((string) $name);
      $name = preg_replace('/[ \t]+/u', ' ', $name);
      $name = preg_replace('/　+/u', '　', $name);

      if ($name === '') {
          return [''];
      }

      if (preg_match('/^(.*?(?:シリーズ|選手権|オープン|カップ|トーナメント|大会))[ 　]+(.+)$/u', $name, $matches)) {
          return [trim($matches[1]), trim($matches[2])];
      }

      if (preg_match('/^(.*?)([Ａ-ＺA-Z]\s*会場[:：].+)$/u', $name, $matches)) {
          return [trim($matches[1]), trim($matches[2])];
      }

      if (mb_strlen($name) <= 28) {
          return [$name];
      }

      $splitPoints = ['　', '：', ':', ' '];
      foreach ($splitPoints as $point) {
          $pos = mb_strrpos($name, $point);
          if ($pos !== false && $pos > 10 && $pos < mb_strlen($name) - 4) {
              return [trim(mb_substr($name, 0, $pos)), trim(mb_substr($name, $pos + mb_strlen($point)))];
          }
      }

      return [mb_substr($name, 0, 28), mb_substr($name, 28)];
  };

  $formatPlayerName = function ($bowler, ?string $licenseTail = null, ?string $fallbackName = null): string {
      $getProp = function ($object, array $candidates): ?string {
          foreach ($candidates as $candidate) {
              if (is_object($object) && isset($object->{$candidate}) && trim((string) $object->{$candidate}) !== '') {
                  return trim((string) $object->{$candidate});
              }
          }

          return null;
      };

      $joinByRule = function (string $surname, string $given): string {
          $surname = preg_replace('/[ 	　]+/u', '', trim($surname));
          $given = preg_replace('/[ 	　]+/u', '', trim($given));
          $full = $surname . $given;
          $length = mb_strlen($full);

          if ($length === 3) {
              return $surname . '　　' . $given;
          }

          if ($length === 4) {
              return $surname . '　' . $given;
          }

          if ($length >= 5) {
              return $full;
          }

          return $full;
      };

      $surname = $getProp($bowler, [
          'last_name_kanji', 'family_name_kanji', 'surname_kanji', 'sei_kanji',
          'name_sei', 'last_name', 'family_name', 'surname', 'sei',
      ]);

      $given = $getProp($bowler, [
          'first_name_kanji', 'given_name_kanji', 'mei_kanji',
          'name_mei', 'first_name', 'given_name', 'mei',
      ]);

      if ($surname !== null && $given !== null) {
          return $joinByRule($surname, $given);
      }

      $normalizedLicense = preg_replace('/\D+/', '', (string) $licenseTail);
      $normalizedLicense = $normalizedLicense === '' ? '' : str_pad(substr($normalizedLicense, -4), 4, '0', STR_PAD_LEFT);
      $rawLicense = trim((string) ($bowler->license_no ?? ''));
      $licensePrefix = '';
      if (preg_match('/^([A-Za-z])/', $rawLicense, $m)) {
          $licensePrefix = strtoupper($m[1]);
      }

      $officialNameByFullLicense = [
          'F:0223' => '長縄多禧子',
          'F:0268' => '酒井　美佳',
          'F:0294' => '宮田　澄子',
          'F:0335' => '森　　ルミ',
          'F:0337' => '前田美津代',
          'F:0352' => '姫路　　麗',
          'F:0361' => '植竹　幸子',
          'F:0364' => '丹羽由香梨',
          'F:0365' => '名和　　秋',
          'F:0371' => '藤田　麻衣',
          'F:0372' => '板倉奈智美',
          'F:0384' => '松永　裕美',
          'F:0450' => '佐藤まさみ',
          'F:0451' => '望月　理江',
          'F:0459' => '森田　和紀',
          'F:0463' => '岸田　有加',
          'F:0469' => '安藤　　瞳',
          'F:0470' => '小林よしみ',
          'F:0473' => '桑原　澄江',
          'F:0475' => '舟本　　舞',
          'F:0476' => '堀内　　綾',
          'F:0486' => '田中　亜実',
          'F:0490' => '大根谷　愛',
          'F:0499' => '本間由佳梨',
          'F:0507' => '寺下　智香',
          'F:0509' => '前屋　瑠美',
          'F:0513' => '鶴井　亜南',
          'F:0514' => '小久保実希',
          'F:0515' => '坂倉にいな',
          'F:0520' => '三浦　美里',
          'F:0521' => '秋光　　楓',
          'F:0523' => '内藤真裕実',
          'F:0524' => '山田　　幸',
          'F:0525' => '宇山　侑花',
          'F:0526' => '久保田彩花',
          'F:0528' => '浅田　梨奈',
          'F:0530' => '飯田　菜々',
          'F:0533' => '川﨑　由意',
          'F:0536' => '殿井ニラワティ',
          'F:0537' => '岩見　彩乃',
          'F:0540' => '倉田　　萌',
          'F:0542' => '小池　沙紀',
          'F:0543' => '松尾　星伽',
          'F:0544' => '坂本　かや',
          'F:0545' => '大嶋　有香',
          'F:0553' => '廣澤　一美',
          'F:0557' => '水谷　若菜',
          'F:0558' => '坂倉　　凜',
          'F:0559' => '霜出　佳奈',
          'F:0560' => '大久保咲桜',
          'F:0561' => '髙橋　美穂',
          'F:0568' => '越智　真南',
          'F:0570' => '本橋　優美',
          'F:0572' => 'チョン･ヨンヒャン',
          'F:0578' => 'キム･ソヒョン',
          'F:0581' => '尾上　萌楓',
          'F:0582' => '中島　瑞葵',
          'F:0583' => '堀井　春花',
          'F:0584' => '原野　萌花',
          'F:0586' => '幸木百合菜',
          'F:0587' => '今井　双葉',
          'F:0588' => '土屋　佑佳',
          'F:0589' => '坂野ニイナ',
          'F:0590' => '横山　実美',
          'F:0594' => '三上　彩奈',
          'F:0595' => '内田　雪月',
          'F:0596' => '竹山　亜希',
          'F:0598' => '近藤　菜帆',
          'F:0599' => '石田　万音',
          'F:0600' => '金子　萌夏',
          'F:0601' => '河村　怜奈',
          'F:0603' => '緒方　彩音',
          'F:0606' => '田中　美佳',
          'F:0609' => '緒方　美空',
          'F:0611' => '新舎菜々美',
          'F:0613' => '秋吉　夕紀',
          'F:0614' => '川田　菜摘',
          'F:0615' => '野仲　美咲',
          'F:0616' => '太　　琳華',
          'F:0617' => '安田明香里',
          'F:0618' => '崎山　穂花',
          'F:0622' => '渡辺　莉央',
          'F:0623' => '井﨑　寛菜',
          'F:0626' => '後藤光々音',
          'F:0635' => '森　ひかり',
      ];

      $officialNameByLicense = [
          '0976' => '髙橋　昭弘',
          '0515' => '木村　紀夫',
          '1463' => '藤　　博文',
          '1477' => '西山　翔悟',
          '1493' => '豊田　晃平',
          '1291' => '渡邊　雄也',
          '0892' => '高城　明文',
          '1365' => '髙橋　俊彦',
          '1428' => '鮫島　　蓮',
          '1469' => '田野岡大夢',
          '0848' => '吉田　文啓',
          '1025' => '斉藤　茂雄',
          '0498' => '浜田　光博',
          '1130' => '岩切　稔純',
          '1268' => '松本　貴臣',
          '1485' => '山﨑　陸斗',
          '1302' => '長岡　義一',
          '1412' => '田原　寬貴',
          '0167' => '星野　宏幸',
          '1393' => '後藤　卓也',
          '1434' => '原口　優馬',
          '1466' => '犬飼　健志',
          '1489' => '藤井　大河',
          '0905' => '太田　隆昌',
          '1212' => '市村　和則',
          '1456' => '太田　貴久',
          '1497' => '山崎　昭太',
          '0906' => '富永　　尚',
          '0806' => '正木　　裕',
          '1379' => '岡田　　良',
          '1277' => '小林　孝至',
          '1315' => '一谷　知広',
          '1479' => '立花　仁貴',
          '1438' => '藤﨑　　周',
          '1240' => '野々山国幸',
          '1401' => '畑野　健人',
          '0431' => '吉川　邦男',
          '0819' => '鈴木　元司',
          '1281' => '松岡　秀法',
          '1488' => '渡邊　元造',
          '1453' => '石田　智輝',
          '0897' => '池元　克美',
          '1492' => '横地　優輝',
          '1458' => '増田　優希',
          '0664' => '川村　直樹',
          '1459' => '山本　義達',
          '1474' => '部谷　嘉朗',
          '1314' => '児玉　侑樹',
          '0944' => '石野　　宏',
          '0885' => '平山　陽一',
          '1429' => '大久保雄矢',
          '1214' => '福丸　哲平',
          '1232' => '鈴木都望耶',
          '1384' => '水野　耕佑',
          '1229' => '上田　晋也',
          '1448' => '木村　謙太',
          '0852' => '今川　雅喜',
          '1394' => '藤村　隆史',
          '1013' => '小金　正治',
          '1296' => '荒井慎太朗',
          '1491' => '吉江奈津希',
          '1190' => '和田　秀和',
          '1467' => '菊田　　樹',
          '1343' => '平岡　勇人',
      ];

      if ($normalizedLicense !== '' && $licensePrefix !== '') {
          $fullKey = $licensePrefix . ':' . $normalizedLicense;
          if (isset($officialNameByFullLicense[$fullKey])) {
              return $officialNameByFullLicense[$fullKey];
          }
      }

      if ($normalizedLicense !== '' && isset($officialNameByLicense[$normalizedLicense])) {
          return $officialNameByLicense[$normalizedLicense];
      }

      $name = trim((string) ($bowler->name_kanji ?? $fallbackName ?? ''));
      $name = preg_replace('/[ 	　]+/u', '', $name);

      if ($name === '') {
          return '-';
      }

      $length = mb_strlen($name);

      if ($length === 3) {
          return mb_substr($name, 0, 1) . '　　' . mb_substr($name, 1);
      }

      if ($length === 4) {
          return mb_substr($name, 0, 2) . '　' . mb_substr($name, 2);
      }

      return $name;
  };

  $formatTemporaryPlayerName = function (?string $name): string {
      $normalized = preg_replace('/[ 	　]+/u', '', trim((string) $name));

      $officialTemporaryNameByName = [
          '野村緋那' => '野村　緋那',
          '坂本真貴子' => '坂本真貴子',
          '戸塚知菜' => '戸塚　知菜',
          '藤林華音' => '藤林　華音',
          '中村華世' => '中村　華世',
      ];

      if ($normalized === '') {
          return '-';
      }

      if (isset($officialTemporaryNameByName[$normalized])) {
          return $officialTemporaryNameByName[$normalized];
      }

      $length = mb_strlen($normalized);
      if ($length === 3) {
          return mb_substr($normalized, 0, 1) . '　　' . mb_substr($normalized, 1);
      }
      if ($length === 4) {
          return mb_substr($normalized, 0, 2) . '　' . mb_substr($normalized, 2);
      }

      return $normalized;
  };

  $buildGroups = function (array $sourceRows, callable $gameLaneResolver): array {
      $groups = [];

      foreach ($sourceRows as $row) {
          $gameLanes = $gameLaneResolver($row);
          $signature = json_encode($gameLanes, JSON_UNESCAPED_UNICODE);
          $lastIndex = count($groups) - 1;

          if ($lastIndex >= 0 && ($groups[$lastIndex]['signature'] ?? null) === $signature) {
              $groups[$lastIndex]['rows'][] = $row;
          } else {
              $groups[] = [
                  'signature' => $signature,
                  'game_lanes' => $gameLanes,
                  'rows' => [$row],
              ];
          }
      }

      return $groups;
  };

  $buildPages = function (array $groups): array {
      $pages = [];
      $currentPage = [];
      $currentPageRowCount = 0;
      $rowsPerPage = 36;

      foreach ($groups as $group) {
          $groupRowCount = count($group['rows']);

          if ($currentPageRowCount > 0 && ($currentPageRowCount + $groupRowCount) > $rowsPerPage) {
              $pages[] = $currentPage;
              $currentPage = [];
              $currentPageRowCount = 0;
          }

          $currentPage[] = $group;
          $currentPageRowCount += $groupRowCount;
      }

      if (count($currentPage) > 0) {
          $pages[] = $currentPage;
      }

      return $pages;
  };

  $boxPlayerCount = (int) ($tournament->box_player_count ?? 0);
  if ($boxPlayerCount < 1) {
      $odd = (int) ($tournament->odd_lane_player_count ?? 0);
      $even = (int) ($tournament->even_lane_player_count ?? 0);
      $boxPlayerCount = ($odd + $even) > 0 ? ($odd + $even) : 0;
  }

  $minutesByBoxPlayers = [
      3 => 27,
      4 => 32,
      5 => 38,
      6 => 50,
  ];

  $gameIntervalMinutes = $minutesByBoxPlayers[$boxPlayerCount] ?? null;

  $buildStartLabels = function (?string $startTime, int $count) use ($gameIntervalMinutes): array {
      $labels = [];

      if (empty($startTime) || $gameIntervalMinutes === null || $count < 1) {
          return $labels;
      }

      try {
          $gameStartAt = \Carbon\Carbon::createFromFormat('H:i', (string) $startTime);

          for ($i = 1; $i <= $count; $i++) {
              $label = $gameStartAt->format('G:i');
              $labels[] = $i === 1 ? $label . 'ｽﾀｰﾄ' : $label . '頃';
              $gameStartAt = $gameStartAt->copy()->addMinutes($gameIntervalMinutes);
          }
      } catch (\Throwable $e) {
          return [];
      }

      return $labels;
  };

  $renderBlocks = [];

  if (count($dayBlocks) > 0) {
      foreach ($dayBlocks as $index => $block) {
          if (!is_array($block)) {
              continue;
          }

          $key = (string) ($block['key'] ?? ('day' . ($index + 1)));
          $gameFrom = (int) ($block['game_from'] ?? 1);
          $gameTo = (int) ($block['game_to'] ?? ($gameFrom + (int)($block['games'] ?? 1) - 1));
          $blockGames = max(1, $gameTo - $gameFrom + 1);
          $groups = $buildGroups($rows, function ($row) use ($key) {
              return $row['day_game_lanes'][$key] ?? $row['game_lanes'] ?? [];
          });

          $renderBlocks[] = [
              'key' => $key,
              'label' => trim((string) ($block['label'] ?? (($index + 1) . '日目'))),
              'game_from' => $gameFrom,
              'game_to' => $gameTo,
              'games' => $blockGames,
              'start_time' => $block['start_time'] ?? null,
              'regular_move_boxes' => $block['regular_move_boxes'] ?? ($settings['regular_move_boxes'] ?? null),
              'pages' => $buildPages($groups),
              'start_labels' => $buildStartLabels($block['start_time'] ?? null, $blockGames),
          ];
      }
  }

  if (count($renderBlocks) < 1) {
      $groups = $buildGroups($rows, fn ($row) => $row['game_lanes'] ?? []);
      $renderBlocks[] = [
          'key' => 'default',
          'label' => '',
          'game_from' => 1,
          'game_to' => $games,
          'games' => $games,
          'start_time' => $settings['start_time'] ?? null,
          'regular_move_boxes' => $settings['regular_move_boxes'] ?? null,
          'pages' => $buildPages($groups),
          'start_labels' => $buildStartLabels($settings['start_time'] ?? null, $games),
      ];
  }

  $asOfText = now()->format('Y.n.j') . '現在';
  $titleLines = $formatTitleLines($tournament->name ?? '');
@endphp
<style>
  .lane-page-wrap {
    background: #f3f4f6;
    padding: 14px 0 40px;
  }

  .lane-paper {
    width: 794px;
    min-height: 1123px;
    margin: 0 auto 24px;
    background: #fff;
    color: #000;
    padding: 28px 25px 24px;
    box-shadow: 0 1px 8px rgba(0,0,0,.14);
    font-family: "Yu Gothic", "YuGothic", "Meiryo", sans-serif;
  }

  .official-title {
    text-align: center;
    font-weight: 900;
    letter-spacing: .16em;
    line-height: 1.2;
    margin: 0;
    font-size: 21px;
  }

  .official-title .title-line {
    display: block;
  }

  .official-subtitle {
    text-align: center;
    font-weight: 900;
    line-height: 1.15;
    margin: 3px 0 0;
    font-size: 12.6px;
  }

  .official-meta {
    text-align: center;
    font-size: 9px;
    font-weight: 700;
    color: #333;
    line-height: 1.1;
    margin-top: 2px;
    height: 12px;
  }

  .official-as-of {
    text-align: right;
    font-size: 9px;
    font-weight: 700;
    margin: -10px 2px 3px 0;
  }

  .official-lane-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 3px solid #000;
    background: #fff;
  }

  .official-lane-table col.start-col { width: 40px; }
  .official-lane-table col.license-col { width: 39px; }
  .official-lane-table col.name-col { width: 88px; }
  .official-lane-table col.kibetsu-col { width: 24px; }
  .official-lane-table col.game-col { width: calc((100% - 191px) / 8); }

  .official-lane-table th,
  .official-lane-table td {
    border: 1.25px solid #000;
    padding: 2px 2px;
    font-size: 9.4px;
    line-height: 1.12;
    vertical-align: middle;
    color: #000;
    white-space: nowrap;
    overflow: hidden;
  }

  .official-lane-table thead th {
    text-align: center;
    font-weight: 900;
    background: #fff;
  }

  .official-lane-table .schedule-label-head {
    font-size: 8.2px;
    font-weight: 900;
    letter-spacing: .02em;
    border-bottom: 1.25px solid #000;
  }

  .official-lane-table .schedule-time-head {
    font-size: 8.2px;
    font-weight: 900;
    letter-spacing: -.03em;
    border-bottom: 1.25px solid #000;
    padding-top: 1px;
    padding-bottom: 1px;
  }

  .official-lane-table .game-label-row th {
    border-bottom: 3px solid #000;
  }

  .official-lane-table .start-cell,
  .official-lane-table .license-cell,
  .official-lane-table .kibetsu-cell {
    text-align: center;
  }

  .official-lane-table .name-cell {
    text-align: center;
    font-weight: 900;
    font-size: 10.2px;
    letter-spacing: .02em;
    padding-left: 1px;
    padding-right: 1px;
  }

  .official-lane-table .game-head {
    text-align: center;
    font-size: 14px;
    font-weight: 900;
  }

  .official-lane-table tbody td {
    height: 24px;
  }

  .official-lane-table .game-cell {
    text-align: center;
    font-size: 12.6px;
    font-weight: 900;
    letter-spacing: .01em;
  }

  .official-lane-table .thick-right {
    border-right: 4px solid #000 !important;
  }

  .official-lane-table tr.group-end td {
    border-bottom: 3px solid #000;
  }

  .official-lane-table .rowspan-game {
    border-bottom: 3px solid #000 !important;
  }

  .no-print .btn {
    white-space: nowrap;
  }

  @media print {
    @page {
      size: A4 portrait;
      margin: 7mm;
    }

    .no-print,
    nav,
    header,
    .navbar {
      display: none !important;
    }

    html,
    body {
      background: #fff !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .lane-page-wrap {
      background: #fff;
      padding: 0;
    }

    .lane-paper {
      width: 100%;
      min-height: auto;
      margin: 0;
      padding: 0;
      box-shadow: none;
      page-break-after: always;
    }

    .lane-paper:last-child {
      page-break-after: auto;
    }

    .official-title {
      font-size: 19px;
    }

    .official-subtitle {
      font-size: 12.5px;
    }

    .official-as-of {
      font-size: 8px;
      margin-top: -10px;
    }

    .official-lane-table th,
    .official-lane-table td {
      font-size: 8.7px;
      padding: 1.4px 1.2px;
      line-height: 1.08;
    }

    .official-lane-table .name-cell {
      font-size: 9.4px;
    }

    .official-lane-table .schedule-label-head,
    .official-lane-table .schedule-time-head {
      font-size: 7.3px;
      padding-top: .6px;
      padding-bottom: .6px;
    }

    .official-lane-table tbody td {
      height: 21.7px;
    }

    .official-lane-table .game-head {
      font-size: 12px;
    }

    .official-lane-table .game-cell {
      font-size: 10.3px;
    }
  }
</style>

<div class="container-fluid lane-page-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print" style="max-width:794px;margin:0 auto;">
    <div>
      <h2 class="mb-1">レーン移動表</h2>
      <div class="text-muted">{{ $tournament->name }}</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()">印刷</button>
      <a href="{{ route('tournaments.draws.index', $tournament->id) }}" class="btn btn-secondary">抽選結果一覧へ戻る</a>
      <a href="{{ route('tournaments.entries.index', $tournament->id) }}" class="btn btn-outline-dark">エントリー一覧へ戻る</a>
      <a href="{{ route('tournaments.edit', $tournament->id) }}" class="btn btn-outline-primary">大会設定を編集</a>
    </div>
  </div>

  @if (empty($settings['enabled']))
    <div class="alert alert-warning no-print" style="max-width:794px;margin:0 auto;">
      この大会はレーン移動表ルールが未設定です。大会編集画面で「レーン移動表を作成する」をONにしてください。
    </div>
  @elseif (count($rows) === 0)
    <div class="alert alert-warning no-print" style="max-width:794px;margin:0 auto;">
      レーンが確定している参加者がいません。先にレーン抽選またはレーン登録を行ってください。
    </div>
  @else
    @foreach ($renderBlocks as $block)
      @foreach ($block['pages'] as $pageIndex => $pageGroups)
        <section class="lane-paper">
          <h1 class="official-title">
            @foreach ($titleLines as $titleLine)
              <span class="title-line">{{ $titleLine }}</span>
            @endforeach
          </h1>
          <div class="official-subtitle">
            {{ $block['label'] !== '' ? $block['label'] : '予選' . $block['games'] . 'Ｇ' }} レーン移動表
          </div>
          <div class="official-meta">
            使用レーン {{ $settings['lane_from'] ?? '-' }}〜{{ $settings['lane_to'] ?? '-' }} ／ 通常移動 {{ $block['regular_move_boxes'] ?? '-' }}BOX
          </div>
          <div class="official-as-of">{{ $asOfText }}</div>

          <table class="official-lane-table">
            <colgroup>
              <col class="start-col">
              <col class="license-col">
              <col class="name-col">
              <col class="kibetsu-col">
              @for ($i = 1; $i <= $block['games']; $i++)
                <col class="game-col">
              @endfor
            </colgroup>
            <thead>
              <tr class="schedule-row">
                <th colspan="4" class="schedule-label-head thick-right">ゲーム進行予定時間</th>
                @for ($i = 1; $i <= $block['games']; $i++)
                  <th class="schedule-time-head {{ $i === 4 ? 'thick-right' : '' }}">{{ $block['start_labels'][$i - 1] ?? '' }}</th>
                @endfor
              </tr>
              <tr class="game-label-row">
                <th class="start-cell">スタート<br>レーン</th>
                <th class="license-cell">ライセンス<br>No.</th>
                <th class="name-cell" style="text-align:center;">氏　名</th>
                <th class="kibetsu-cell thick-right">期</th>
                @for ($i = 0; $i < $block['games']; $i++)
                  @php $game = $block['game_from'] + $i; @endphp
                  <th class="game-head {{ ($i + 1) === 4 ? 'thick-right' : '' }}">{{ $game }}Ｇ目</th>
                @endfor
              </tr>
            </thead>
            <tbody>
              @foreach ($pageGroups as $group)
                @php
                  $groupRows = $group['rows'] ?? [];
                  $rowspan = max(1, count($groupRows));
                  $gameLanes = $group['game_lanes'] ?? [];
                @endphp

                @foreach ($groupRows as $rowIndex => $row)
                  @php
                    $bowler = $row['bowler'] ?? null;
                    $entry = $row['entry'] ?? null;
                    $licenseTail = (string) ($row['license_tail'] ?? '');
                    $displayLicense = $licenseTail === 'アマ' ? 'アマ' : ltrim($licenseTail, '0');
                    $displayLicense = $displayLicense === '' ? ($licenseTail ?? '') : $displayLicense;
                    $fallbackName = $row['display_name'] ?? ($entry->display_name ?? $entry->name ?? null);
                    if (($row['participant_type'] ?? null) === 'amateur' || $displayLicense === 'アマ') {
                        $displayName = $formatTemporaryPlayerName($fallbackName);
                    } else {
                        $displayName = $formatPlayerName($bowler, $row['license_tail'] ?? null, $fallbackName);
                    }
                  @endphp
                  <tr class="{{ $rowIndex === ($rowspan - 1) ? 'group-end' : '' }}">
                    <td class="start-cell">{{ $row['start_lane_label'] }}</td>
                    <td class="license-cell">{{ $displayLicense }}</td>
                    <td class="name-cell">{{ $displayName }}</td>
                    <td class="kibetsu-cell thick-right">{{ $row['kibetsu'] ?? '-' }}</td>

                    @if ($rowIndex === 0)
                      @for ($i = 0; $i < $block['games']; $i++)
                        @php $game = $block['game_from'] + $i; @endphp
                        <td rowspan="{{ $rowspan }}" class="game-cell rowspan-game {{ ($i + 1) === 4 ? 'thick-right' : '' }}">
                          {{ $gameLanes[$game] ?? '-' }}
                        </td>
                      @endfor
                    @endif
                  </tr>
                @endforeach
              @endforeach
            </tbody>
          </table>
        </section>
      @endforeach
    @endforeach
  @endif
</div>
@endsection
