<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $query = Tournament::query();

        if ($request->filled('name'))        $query->where('name', 'like', '%' . $request->name . '%');
        if ($request->filled('start_date'))  $query->whereDate('start_date', $request->start_date);
        if ($request->filled('venue_name'))  $query->where('venue_name', 'like', '%' . $request->venue_name . '%');

        $tournaments = $query->get();
        return view('tournaments.index', compact('tournaments'));
    }

    public function create(Request $request)
    {
        $prefill = $request->session()->get('tournament_prefill', []);
        return view('tournaments.create', ['prefill' => $prefill]);
    }

    public function clone($id)
    {
        $src = Tournament::with(['organizations','files'])->findOrFail($id);

        $prefill = $src->only([
            'name','venue_id','venue_name','venue_address','venue_tel','venue_fax',
            'gender','official_type','title_category',
            'broadcast','streaming','prize','admission_fee',
            'spectator_policy','entry_conditions','materials','previous_event',
            'broadcast_url','streaming_url','previous_event_url',
            'hero_image_path','poster_images','extra_venues',
            // タイトル左ロゴも下書きとして保持（編集画面でプレビューしたい需要に対応）
            'title_logo_path',
        ]);

        $prefill['org'] = $src->organizations->map(function($o){
            return [
                'category'   => $o->category,
                'name'       => $o->name,
                'url'        => $o->url,
                'sort_order' => $o->sort_order,
            ];
        })->values()->all();

        session()->flash('tournament_prefill', $prefill);
        return redirect()->route('tournaments.create')
            ->with('success','前回大会の内容を下書きにコピーしました。（日付は空にしています）');
    }

    public function show($id)
    {
        $tournament = Tournament::with(['organizations','files','venue'])->findOrFail($id);
        return view('tournaments.show', compact('tournament'));
    }

    public function edit($id)
    {
        $tournament = Tournament::with(['organizations','files','venue'])->findOrFail($id);
        return view('tournaments.edit', compact('tournament'));
    }

    // ====== 入力バリデーション＋整形 ======
    private function validateAndNormalize(Request $request): array
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'venue_name'     => 'nullable|string',
            'venue_address'  => 'nullable|string',
            'venue_tel'      => 'nullable|string',
            'venue_fax'      => 'nullable|string',
            'gender'         => 'required|in:M,F,X',
            'official_type'  => 'required|in:official,approved,other',
            'title_category' => 'nullable|in:normal,season_trial,excluded',
            'entry_start'    => 'nullable|date',
            'entry_end'      => 'nullable|date|after_or_equal:entry_start',
            'inspection_required' => 'nullable|boolean',

            'spectator_policy'    => 'nullable|in:paid,free,none',
            'prize'               => 'nullable|string',
            'admission_fee'       => 'nullable|string',
            'broadcast'           => 'nullable|string',
            'streaming'           => 'nullable|string',
            'broadcast_url'       => 'nullable|string|max:255',
            'streaming_url'       => 'nullable|string|max:255',
            'previous_event'      => 'nullable|string',
            'previous_event_url'  => 'nullable|string|max:255',
            'entry_conditions'    => 'nullable|string',
            'materials'           => 'nullable|string',

            'venue_id'            => 'nullable|integer|exists:venues,id',

            'extra_venues'                => 'nullable|array|max:4',
            'extra_venues.*.venue_id'     => 'nullable|integer|exists:venues,id',
            'extra_venues.*.name'         => 'nullable|string|max:255',
            'extra_venues.*.address'      => 'nullable|string|max:255',
            'extra_venues.*.tel'          => 'nullable|string|max:50',
            'extra_venues.*.fax'          => 'nullable|string|max:50',
            'extra_venues.*.website_url'  => 'nullable|string|max:255',
            'extra_venues.*.memo'         => 'nullable|string|max:2000',

            // サイドバー
            'schedule'           => 'sometimes|array',
            'awards'             => 'sometimes|array',
            // タイトル左ロゴ（画像）…略
        ]);

        // audience → spectator_policy 互換
        if ($request->filled('audience') && empty($validated['spectator_policy'])) {
            $map = [
                '可(有料)' => 'paid','可（有料）' => 'paid',
                '可(無料)' => 'free','可（無料）' => 'free',
                '不可'    => 'none',
            ];
            $aud = trim((string)$request->input('audience'));
            if (isset($map[$aud])) $validated['spectator_policy'] = $map[$aud];
        }

        // 申込期間の時刻補完
        $validated = array_merge($validated, [
            'entry_start' => $request->filled('entry_start')
                ? Carbon::parse($request->input('entry_start'))->setTime(10, 0)
                : null,
            'entry_end' => $request->filled('entry_end')
                ? Carbon::parse($request->input('entry_end'))->setTime(23, 59)
                : null,
            'inspection_required' => $request->boolean('inspection_required'),
        ]);

        // 会場ID → 自動補完
        if ($request->filled('venue_id')) {
            $venue = \App\Models\Venue::find($request->input('venue_id'));
            if ($venue) {
                $validated['venue_name']    = $validated['venue_name']    ?? $venue->name;
                $validated['venue_address'] = $validated['venue_address'] ?? $venue->address;
                $validated['venue_tel']     = $validated['venue_tel']     ?? $venue->tel;
                $validated['venue_fax']     = $validated['venue_fax']     ?? $venue->fax;
            }
        }

        // URL スキーム補完
        foreach (['broadcast_url','streaming_url','previous_event_url'] as $k) {
            if (!empty($validated[$k]) && !preg_match('~^https?://~i', $validated[$k])) {
                $validated[$k] = 'https://' . ltrim($validated[$k]);
            }
        }

        return $validated;
    }

    /**
     * 組織入力をどの形で来ても吸収する。
     * category+name+url でユニーク化。
     */
    private function buildOrgRowsAndTexts(Request $request): array
    {
        $rows = [];
        $texts = [
            'host'            => [],
            'special_sponsor' => [],
            'sponsor'         => [],
            'support'         => [],
            'cooperation'     => [],
        ];

        $seen = []; // 重複排除キー

        $add = function(string $cat, ?string $name, ?string $url, int $order = 0) use (&$rows,&$texts,&$seen) {
            $name = trim((string)$name);
            if ($name === '' || !isset($texts[$cat])) return;
            if ($url && !preg_match('~^https?://~i', $url)) $url = 'https://' . ltrim($url);

            $key = strtolower($cat.'|'.$name.'|'.($url ?? ''));
            if (isset($seen[$key])) return;
            $seen[$key] = true;

            $rows[] = new \App\Models\TournamentOrganization([
                'category'   => $cat,
                'name'       => $name,
                'url'        => $url ?: null,
                'sort_order' => $order,
            ]);
            $texts[$cat][] = $name;
        };

        // A) org[][category|name|url]
        $flat = $request->input('org');
        if (is_array($flat) && array_key_exists(0, $flat)) {
            foreach ($flat as $i => $r) {
                $add((string)($r['category'] ?? ''), $r['name'] ?? null, $r['url'] ?? null, (int)($r['sort_order'] ?? $i));
            }
        }
        // B) org[host][] など
        if (is_array($flat) && !array_key_exists(0, $flat)) {
            foreach (array_keys($texts) as $cat) {
                $list = $flat[$cat] ?? null;
                if (!is_array($list)) continue;
                $j = 0;
                foreach ($list as $item) {
                    if (is_array($item)) {
                        $add($cat, $item['name'] ?? null, $item['url'] ?? null, $j++);
                    } elseif (is_string($item) && $item !== '') {
                        $add($cat, $item, null, $j++);
                    }
                }
            }
        }
        // C) 別名
        foreach (array_keys($texts) as $cat) {
            $key = 'org_' . $cat;
            if (!$request->has($key)) continue;
            $list = $request->input($key);
            if (is_array($list) && array_key_exists(0, $list) && is_array($list[0])) {
                $k = 0;
                foreach ($list as $item) $add($cat, $item['name'] ?? null, $item['url'] ?? null, $k++);
            } elseif (is_array($list) && isset($list['name']) && is_array($list['name'])) {
                $names = $list['name'];
                $urls  = is_array($list['url'] ?? null) ? $list['url'] : [];
                foreach ($names as $idx => $nm) $add($cat, $nm, $urls[$idx] ?? null, $idx);
            } elseif (is_array($list)) {
                foreach ($list as $idx => $nm) $add($cat, is_string($nm) ? $nm : null, null, $idx);
            }
        }

        foreach ($texts as $k => $arr) {
            $texts[$k] = $arr ? implode(' / ', array_values(array_unique($arr))) : null;
        }

        return ['rows' => $rows, 'text' => $texts];
    }

    /**
     * サイドバー：日程・成績
     * - ラベルのみOK（見出し）
     * - URL > PDF > 既存維持 の優先でhref
     * - 区切り線（separator=1）をサポート
     * - 日付（date）は“表示用の自由文字列”として扱う（9/10(木) 等）
     */
    private function buildSidebarSchedule(Request $request): array
    {
        $out = [];
        $rows  = (array)$request->input('schedule', []);
        $files = $request->file('schedule_files', []);
        $keeps = (array)$request->input('schedule_keep', []); // [i][keep], [i][href]

        foreach ($rows as $i => $r) {
            $date  = trim((string)($r['date']  ?? ''));
            $label = trim((string)($r['label'] ?? ''));
            $url   = trim((string)($r['url']   ?? ''));
            $sep   = !empty($r['separator']); // bool

            $href = null;
            if (!$sep) { // 区切り線のときはリンク評価しない
                if ($url !== '') {
                    $href = preg_match('~^https?://~i', $url) ? $url : ('https://'.ltrim($url));
                } elseif (!empty($files[$i])) {
                    $href = $files[$i]->store('tournament_pdfs', 'public');
                } elseif (!empty($keeps[$i]['keep']) && !empty($keeps[$i]['href'])) {
                    $href = $keeps[$i]['href']; // 既存を維持
                }
            }

            // 条件：
            // - 区切り線 もしくは
            // - ラベル or リンクがある
            if (!$sep && $label === '' && $href === null) continue;

            $out[] = [
                'date'      => $date,
                'label'     => $label,
                'href'      => $href,
                'separator' => $sep,
            ];
        }

        // 重複除去（date+label+href+sep）
        $uniq = [];
        $dedup = [];
        foreach ($out as $r) {
            $k = ($r['date'] ?? '').'|'.($r['label'] ?? '').'|'.($r['href'] ?? '').'|'.(!empty($r['separator'])?'1':'0');
            if (isset($uniq[$k])) continue;
            $uniq[$k] = true;
            $dedup[] = $r;
        }
        return $dedup;
    }

    /** サイドバー：褒章（既存写真維持をサポート） */
    private function buildAwardHighlights(Request $request): array
    {
        $out   = [];
        $rows  = (array)$request->input('awards', []);
        $files = $request->file('award_files', []);
        $keeps = (array)$request->input('awards_keep', []); // [i][photo]

        foreach ($rows as $i => $r) {
            $type   = in_array(($r['type'] ?? ''), ['perfect','series800','split710'], true) ? $r['type'] : 'perfect';
            $player = trim((string)($r['player'] ?? ''));
            $game   = trim((string)($r['game']   ?? ''));
            $lane   = trim((string)($r['lane']   ?? ''));
            $note   = trim((string)($r['note']   ?? ''));
            $title  = trim((string)($r['title']  ?? ''));

            // 写真：新規 > 既存維持 > なし
            $photo = null;
            if (!empty($files[$i])) {
                $photo = $files[$i]->store('tournament_awards', 'public');
            } elseif (!empty($keeps[$i]['photo'])) {
                $photo = $keeps[$i]['photo'];
            }

            // タイトル/選手/写真のいずれかがあれば採用
            if ($player === '' && $photo === null && $title === '') continue;

            $out[] = [
                'type'  => $type,
                'player'=> $player,
                'game'  => $game,
                'lane'  => $lane,
                'note'  => $note,
                'title' => $title,
                'photo' => $photo,
            ];
        }
        return $out;
    }

    /** ギャラリー／簡易速報PDF を併合（keep 指定対応） */
    private function buildGalleryAndResults(Request $request, Tournament $t = null): array
    {
        $gallery = [];
        $results = [];

        if (is_array($request->input('__keep_gallery'))) {
            foreach ($request->input('__keep_gallery') as $g) {
                if (!empty($g['photo'])) $gallery[] = ['photo'=>$g['photo'], 'title'=>$g['title'] ?? null];
            }
        } elseif ($t && is_array($t->gallery_items)) {
            $gallery = $t->gallery_items;
        }

        if (is_array($request->input('__keep_results'))) {
            foreach ($request->input('__keep_results') as $r) {
                if (!empty($r['file'])) $results[] = ['file'=>$r['file'], 'title'=>$r['title'] ?? null];
            }
        } elseif ($t && is_array($t->simple_result_pdfs)) {
            $results = $t->simple_result_pdfs;
        }

        if ($request->hasFile('gallery_files')) {
            $titles = (array)$request->input('gallery_titles', []);
            if (count($titles) === 1 && is_string($titles[0]) && str_contains($titles[0], "\n")) {
                $titles = array_map('trim', preg_split('/\r\n|\n|\r/u', $titles[0]));
            }
            foreach ($request->file('gallery_files') as $i => $f) {
                if (!$f) continue;
                $path = $f->store('tournament_gallery', 'public');
                $gallery[] = ['photo'=>$path, 'title'=>$titles[$i] ?? null];
            }
        }

        if ($request->hasFile('result_pdfs')) {
            $titles = (array)$request->input('result_titles', []);
            if (count($titles) === 1 && is_string($titles[0]) && str_contains($titles[0], "\n")) {
                $titles = array_map('trim', preg_split('/\r\n|\n|\r/u', $titles[0]));
            }
            foreach ($request->file('result_pdfs') as $i => $f) {
                if (!$f) continue;
                $path = $f->store('tournament_pdfs', 'public');
                $results[] = ['file'=>$path, 'title'=>$titles[$i] ?? null];
            }
        }

        return [$gallery, $results];
    }

    /** ★ 終了後：優勝者・トーナメント（カード）— 複数写真対応＆後方互換 */
    private function buildResultCards(Request $request, Tournament $t = null): array
    {
        $rows       = (array)$request->input('result_cards', []);
        $photoFiles = $request->file('result_card_photos', []); // [i][] or [i]
        $pdfFiles   = $request->file('result_card_files', []);  // [i]
        $keeps      = (array)$request->input('result_card_keep', []); // [i][photos][], [i][photo], [i][file]

        $out = [];
        foreach ($rows as $i => $r) {
            $title  = trim((string)($r['title']  ?? ''));
            $player = trim((string)($r['player'] ?? ''));
            $balls  = trim((string)($r['balls']  ?? ''));
            $note   = trim((string)($r['note']   ?? ''));
            $url    = trim((string)($r['url']    ?? ''));

            if ($url !== '' && !preg_match('~^https?://~i', $url)) {
                $url = 'https://' . ltrim($url);
            }

            // --- 写真：既存の“残す” + 新規アップロード（複数）
            $photos = [];
            if (!empty($keeps[$i]['photos']) && is_array($keeps[$i]['photos'])) {
                foreach ($keeps[$i]['photos'] as $p) {
                    if ($p !== null && $p !== '') $photos[] = $p;
                }
            }
            // 旧データ互換：単一 photo を保持指定で送ってきた場合
            if (empty($keeps[$i]['photos']) && !empty($keeps[$i]['photo'])) {
                $photos[] = $keeps[$i]['photo'];
            }
            if (isset($photoFiles[$i])) {
                $slot = $photoFiles[$i];
                if (is_array($slot)) {
                    foreach ($slot as $pf) {
                        if (!$pf) continue;
                        $photos[] = $pf->store('tournament_results', 'public');
                    }
                } else {
                    // 単一inputだった旧フォーム互換
                    if ($slot) $photos[] = $slot->store('tournament_results', 'public');
                }
            }

            // --- PDF：新規 > 既存維持
            $filePath = null;
            if (!empty($pdfFiles[$i])) {
                $filePath = $pdfFiles[$i]->store('tournament_pdfs', 'public');
            } elseif (!empty($keeps[$i]['file'])) {
                $filePath = $keeps[$i]['file'];
            }

            // 何も無ければスキップ（見出し/選手/写真/ファイル/備考等のいずれか）
            if ($title === '' && $player === '' && $balls === '' && $note === '' && $url === '' && !$photos && !$filePath) {
                continue;
            }

            // 後方互換：最初の1枚を 'photo' にも入れておく（既存のshowが単一想定でも崩れない）
            $out[] = [
                'title'  => $title,
                'player' => $player,
                'balls'  => $balls,
                'note'   => $note,
                'url'    => $url,
                'photos' => $photos,                 // ★ 新フィールド（複数枚）
                'photo'  => $photos[0] ?? null,      // ★ 互換のため残す
                'file'   => $filePath,
            ];
        }
        return $out;
    }

    public function store(Request $request)
    {
        $validated = $this->validateAndNormalize($request);

        // 画像：トップ
        if ($request->hasFile('hero_image')) {
            $validated['hero_image_path'] = $request->file('hero_image')->store('posters', 'public');
        }
        // 画像：タイトル左ロゴ（編集時保持のための保存先を統一）
        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('title_logos', 'public');
        }
        // 画像：旧互換（単体）
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }
        // 画像：ポスター複数
        $posterPaths = [];
        if ($request->hasFile('posters')) {
            foreach ($request->file('posters') as $pf) {
                if ($pf) $posterPaths[] = $pf->store('posters', 'public');
            }
        }
        if ($posterPaths) $validated['poster_images'] = $posterPaths;

        // ★ タイトル左ロゴ（保持＆編集時も消えないよう保存）
        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('posters', 'public');
        }

        // 右サイド
        $validated['sidebar_schedule']  = $this->buildSidebarSchedule($request);
        $validated['award_highlights']  = $this->buildAwardHighlights($request);

        // ギャラリー／簡易速報PDF
        [$gallery, $results] = $this->buildGalleryAndResults($request, null);
        if ($gallery) $validated['gallery_items'] = $gallery;
        if ($results) $validated['simple_result_pdfs'] = $results;

        // ★ 終了後：優勝者・トーナメント（カード）
        $cards = $this->buildResultCards($request, null);
        if ($cards) $validated['result_cards'] = $cards;

        $t = Tournament::create($validated);

        // 主催/協賛リンク（再掲）
        $org = $this->buildOrgRowsAndTexts($request);
        if (!empty($org['rows'])) $t->organizations()->saveMany($org['rows']);
        $t->fill([
            'host'            => $org['text']['host'],
            'special_sponsor' => $org['text']['special_sponsor'],
            'sponsor'         => $org['text']['sponsor'],
            'support'         => $org['text']['support'],
        ])->save();

        // 既定PDF
        $filesToStore = [
            'outline_public' => ['type' => 'outline_public', 'visibility' => 'public',  'title' => '大会要項（一般）'],
            'outline_player' => ['type' => 'outline_player', 'visibility' => 'members', 'title' => '大会要項（選手）'],
            'oil_pattern'    => ['type' => 'oil_pattern',    'visibility' => 'public',  'title' => 'オイルパターン表'],
        ];
        foreach ($filesToStore as $inputName => $meta) {
            if ($request->hasFile($inputName)) {
                $path = $request->file($inputName)->store('tournament_pdfs', 'public');
                $t->files()->create([
                    'type'       => $meta['type'],
                    'title'      => $meta['title'],
                    'file_path'  => $path,
                    'visibility' => $meta['visibility'],
                    'sort_order' => 0,
                ]);
            }
        }
        // カスタム
        if ($request->hasFile('custom_files')) {
            $titles = (array)$request->input('custom_titles', []);
            $i = 0;
            foreach ($request->file('custom_files') as $file) {
                if (!$file) continue;
                $title = $titles[$i] ?? '資料'.($i+1);
                $path = $file->store('tournament_pdfs', 'public');
                $t->files()->create([
                    'type' => 'custom',
                    'title' => $title,
                    'file_path' => $path,
                    'visibility' => 'public',
                    'sort_order' => $i,
                ]);
                $i++;
            }
        }

        return redirect()->route('tournaments.show', $t->id)
            ->with('success', '大会が登録されました');
    }

    public function update(Request $request, $id)
    {
        $t = Tournament::with(['organizations','files'])->findOrFail($id);
        $validated = $this->validateAndNormalize($request);

        if ($request->hasFile('hero_image')) {
            $validated['hero_image_path'] = $request->file('hero_image')->store('posters', 'public');
        }
        // タイトル左ロゴ：新規があれば差し替え、なければ既存を維持
        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('title_logos', 'public');
        } else {
            // 送信が無い＝保持。※明示的な「削除」UIを設けていないため常に維持
            $validated['title_logo_path'] = $t->title_logo_path;
        }
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }
        $posterPaths = $t->poster_images ?? [];
        if ($request->hasFile('posters')) {
            foreach ($request->file('posters') as $pf) {
                if ($pf) $posterPaths[] = $pf->store('posters', 'public');
            }
            $validated['poster_images'] = $posterPaths;
        }

        // ★ タイトル左ロゴ（未指定なら既存維持、指定時は差し替え）
        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('posters', 'public');
        } else {
            // 送信自体が無ければ既存値を維持（上書き防止）
            $validated['title_logo_path'] = $t->title_logo_path;
        }

        // サイドバー（フォームが送ってこなかった場合は既存維持）
        $newSchedule = $this->buildSidebarSchedule($request);
        if ($request->has('schedule')) {
            $validated['sidebar_schedule'] = $newSchedule;
        } else {
            $validated['sidebar_schedule'] = $t->sidebar_schedule;
        }
        $newAwards = $this->buildAwardHighlights($request);
        if ($request->has('awards')) {
            $validated['award_highlights'] = $newAwards;
        } else {
            $validated['award_highlights'] = $t->award_highlights;
        }

        // ギャラリー／簡易速報PDF（既存 + 追加）
        [$gallery, $results] = $this->buildGalleryAndResults($request, $t);
        $validated['gallery_items'] = $gallery;
        $validated['simple_result_pdfs'] = $results;

        // ★ 終了後：優勝者・トーナメント（フォームが来なければ既存維持）
        if ($request->has('result_cards')) {
            $validated['result_cards'] = $this->buildResultCards($request, $t);
        } else {
            $validated['result_cards'] = $t->result_cards;
        }

        $t->update($validated);

        // 主催/協賛リンク：同期（全削除→再作成）＋ 旧カラム同期
        $t->organizations()->delete();
        $org = $this->buildOrgRowsAndTexts($request);
        if (!empty($org['rows'])) $t->organizations()->saveMany($org['rows']);
        $t->fill([
            'host'            => $org['text']['host'],
            'special_sponsor' => $org['text']['special_sponsor'],
            'sponsor'         => $org['text']['sponsor'],
            'support'         => $org['text']['support'],
        ])->save();

        // 既定PDF（差し替え）
        $filesToStore = [
            'outline_public' => ['type' => 'outline_public', 'visibility' => 'public',  'title' => '大会要項（一般）'],
            'outline_player' => ['type' => 'outline_player', 'visibility' => 'members', 'title' => '大会要項（選手）'],
            'oil_pattern'    => ['type' => 'oil_pattern',    'visibility' => 'public',  'title' => 'オイルパターン表'],
        ];
        foreach ($filesToStore as $inputName => $meta) {
            if ($request->hasFile($inputName)) {
                $path = $request->file($inputName)->store('tournament_pdfs', 'public');
                $t->files()->updateOrCreate(
                    ['type' => $meta['type']],
                    ['title' => $meta['title'], 'file_path' => $path, 'visibility' => $meta['visibility'], 'sort_order' => 0]
                );
            }
        }
        // カスタム append
        if ($request->hasFile('custom_files')) {
            $titles = (array)$request->input('custom_titles', []);
            $i = 0;
            foreach ($request->file('custom_files') as $file) {
                if (!$file) continue;
                $title = $titles[$i] ?? '資料'.($i+1);
                $path = $file->store('tournament_pdfs', 'public');
                $t->files()->create([
                    'type' => 'custom',
                    'title' => $title,
                    'file_path' => $path,
                    'visibility' => 'public',
                    'sort_order' => $i,
                ]);
                $i++;
            }
        }

        return redirect()->route('tournaments.show', $t->id)
            ->with('success', '大会情報を更新しました。');
    }

    /**
     * 管理者用：大会の完全削除
     */
    public function destroy($id)
    {
        $t = Tournament::with(['organizations','files','prizeDistributions','pointDistributions','entries'])
            ->findOrFail($id);

        DB::transaction(function () use ($t) {
            // ストレージ上の関連ファイルを削除
            $paths = [];

            if ($t->image_path)       $paths[] = $t->image_path;
            if ($t->hero_image_path)  $paths[] = $t->hero_image_path;
            if ($t->title_logo_path)  $paths[] = $t->title_logo_path;
            if (is_array($t->poster_images)) {
                foreach ($t->poster_images as $p) $paths[] = $p;
            }
            if (is_array($t->gallery_items)) {
                foreach ($t->gallery_items as $gi) {
                    if (!empty($gi['photo'])) $paths[] = $gi['photo'];
                }
            }
            if (is_array($t->simple_result_pdfs)) {
                foreach ($t->simple_result_pdfs as $ri) {
                    if (!empty($ri['file'])) $paths[] = $ri['file'];
                }
            }
            foreach ($t->files as $f) {
                if ($f->file_path) $paths[] = $f->file_path;
            }

            $paths = array_values(array_unique($paths));
            foreach ($paths as $p) {
                try { Storage::disk('public')->delete($p); } catch (\Throwable $e) { /* ignore */ }
            }

            // 子テーブル削除
            $t->organizations()->delete();
            $t->files()->delete();
            if (method_exists($t, 'prizeDistributions')) $t->prizeDistributions()->delete();
            if (method_exists($t, 'pointDistributions')) $t->pointDistributions()->delete();
            if (method_exists($t, 'entries'))             $t->entries()->delete();

            // 本体削除
            $t->delete();
        });

        return redirect()->route('tournaments.index')->with('success', '大会を削除しました。');
    }
}
