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

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('start_date')) {
            $query->whereDate('start_date', $request->start_date);
        }
        if ($request->filled('venue_name')) {
            $query->where('venue_name', 'like', '%' . $request->venue_name . '%');
        }

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
        $src = Tournament::with(['organizations', 'files'])->findOrFail($id);

        $prefill = $src->only([
            'name', 'venue_id', 'venue_name', 'venue_address', 'venue_tel', 'venue_fax',
            'gender', 'official_type', 'title_category',
            'broadcast', 'streaming', 'prize', 'admission_fee',
            'spectator_policy', 'entry_conditions', 'materials', 'previous_event',
            'broadcast_url', 'streaming_url', 'previous_event_url',
            'hero_image_path', 'poster_images', 'extra_venues',
            'title_logo_path',

            'use_shift_draw',
            'shift_codes',
            'accept_shift_preference',
            'shift_draw_open_at',
            'shift_draw_close_at',
            'use_lane_draw',
            'lane_draw_open_at',
            'lane_draw_close_at',
            'lane_from',
            'lane_to',
            'lane_assignment_mode',
            'box_player_count',
            'odd_lane_player_count',
            'even_lane_player_count',
        ]);

        $prefill['org'] = $src->organizations->map(function ($o) {
            return [
                'category'   => $o->category,
                'name'       => $o->name,
                'url'        => $o->url,
                'sort_order' => $o->sort_order,
            ];
        })->values()->all();

        session()->flash('tournament_prefill', $prefill);

        return redirect()->route('tournaments.create')
            ->with('success', '前回大会の内容を下書きにコピーしました。（日付は空にしています）');
    }

    public function show($id)
    {
        $tournament = Tournament::with(['organizations', 'files', 'venue'])->findOrFail($id);

        return view('tournaments.show', compact('tournament'));
    }

    public function edit($id)
    {
        $tournament = Tournament::with(['organizations', 'files', 'venue'])->findOrFail($id);

        return view('tournaments.edit', compact('tournament'));
    }

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

            'use_shift_draw' => 'nullable|boolean',
            'shift_codes' => 'nullable|string|max:255',
            'accept_shift_preference' => 'nullable|boolean',
            'shift_draw_open_at' => 'nullable|date',
            'shift_draw_close_at' => 'nullable|date|after_or_equal:shift_draw_open_at',

            'use_lane_draw' => 'nullable|boolean',
            'lane_draw_open_at' => 'nullable|date',
            'lane_draw_close_at' => 'nullable|date|after_or_equal:lane_draw_open_at',
            'lane_from' => 'nullable|integer|min:1',
            'lane_to' => 'nullable|integer|gte:lane_from|max:999',
            'lane_assignment_mode' => 'nullable|in:single_lane,box',
            'box_player_count' => 'nullable|integer|min:1|max:12',
            'odd_lane_player_count' => 'nullable|integer|min:1|max:12',
            'even_lane_player_count' => 'nullable|integer|min:1|max:12',

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

            'schedule'           => 'sometimes|array',
            'awards'             => 'sometimes|array',
        ]);

        if ($request->filled('audience') && empty($validated['spectator_policy'])) {
            $map = [
                '可(有料)' => 'paid',
                '可（有料）' => 'paid',
                '可(無料)' => 'free',
                '可（無料）' => 'free',
                '不可' => 'none',
            ];
            $aud = trim((string) $request->input('audience'));
            if (isset($map[$aud])) {
                $validated['spectator_policy'] = $map[$aud];
            }
        }

        $shiftCodes = collect(explode(',', (string) ($validated['shift_codes'] ?? '')))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->implode(',');

        $validated = array_merge($validated, [
            'entry_start' => $request->filled('entry_start')
                ? Carbon::parse($request->input('entry_start'))->setTime(10, 0)
                : null,
            'entry_end' => $request->filled('entry_end')
                ? Carbon::parse($request->input('entry_end'))->setTime(23, 59)
                : null,
            'inspection_required' => $request->boolean('inspection_required'),

            'use_shift_draw' => $request->boolean('use_shift_draw'),
            'shift_codes' => $shiftCodes !== '' ? $shiftCodes : null,
            'accept_shift_preference' => $request->boolean('use_shift_draw') && $request->boolean('accept_shift_preference'),
            'shift_draw_open_at' => $request->filled('shift_draw_open_at')
                ? Carbon::parse($request->input('shift_draw_open_at'))
                : null,
            'shift_draw_close_at' => $request->filled('shift_draw_close_at')
                ? Carbon::parse($request->input('shift_draw_close_at'))
                : null,

            'use_lane_draw' => $request->boolean('use_lane_draw'),
            'lane_draw_open_at' => $request->filled('lane_draw_open_at')
                ? Carbon::parse($request->input('lane_draw_open_at'))
                : null,
            'lane_draw_close_at' => $request->filled('lane_draw_close_at')
                ? Carbon::parse($request->input('lane_draw_close_at'))
                : null,
            'lane_assignment_mode' => $validated['lane_assignment_mode'] ?? 'single_lane',
        ]);

        if ($validated['use_shift_draw'] && blank($validated['shift_codes'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'shift_codes' => 'シフト抽選を使う場合は、シフト候補を入力してください。',
            ]);
        }

        if ($validated['use_lane_draw']) {
            if (is_null($validated['lane_from'] ?? null) || is_null($validated['lane_to'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lane_from' => 'レーン抽選を使う場合は、使用レーン範囲を入力してください。',
                ]);
            }

            if (($validated['lane_assignment_mode'] ?? 'single_lane') === 'box') {
                $box = (int) ($validated['box_player_count'] ?? 0);
                $odd = (int) ($validated['odd_lane_player_count'] ?? 0);
                $even = (int) ($validated['even_lane_player_count'] ?? 0);

                if ($box < 1 || $odd < 1 || $even < 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'box_player_count' => 'BOX運用を使う場合は、BOX人数と奇数/偶数レーン人数を入力してください。',
                    ]);
                }

                if (($odd + $even) !== $box) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'box_player_count' => 'BOX人数は「奇数レーン人数 + 偶数レーン人数」と一致させてください。',
                    ]);
                }
            }
        }

        if ($request->filled('venue_id')) {
            $venue = \App\Models\Venue::find($request->input('venue_id'));
            if ($venue) {
                $validated['venue_name']    = $validated['venue_name'] ?? $venue->name;
                $validated['venue_address'] = $validated['venue_address'] ?? $venue->address;
                $validated['venue_tel']     = $validated['venue_tel'] ?? $venue->tel;
                $validated['venue_fax']     = $validated['venue_fax'] ?? $venue->fax;
            }
        }

        foreach (['broadcast_url', 'streaming_url', 'previous_event_url'] as $k) {
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

        $seen = [];

        $add = function (string $cat, ?string $name, ?string $url, int $order = 0) use (&$rows, &$texts, &$seen) {
            $name = trim((string) $name);
            if ($name === '' || !isset($texts[$cat])) {
                return;
            }
            if ($url && !preg_match('~^https?://~i', $url)) {
                $url = 'https://' . ltrim($url);
            }

            $key = strtolower($cat . '|' . $name . '|' . ($url ?? ''));
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            $rows[] = new \App\Models\TournamentOrganization([
                'category'   => $cat,
                'name'       => $name,
                'url'        => $url ?: null,
                'sort_order' => $order,
            ]);
            $texts[$cat][] = $name;
        };

        $flat = $request->input('org');
        if (is_array($flat) && array_key_exists(0, $flat)) {
            foreach ($flat as $i => $r) {
                $add((string) ($r['category'] ?? ''), $r['name'] ?? null, $r['url'] ?? null, (int) ($r['sort_order'] ?? $i));
            }
        }

        if (is_array($flat) && !array_key_exists(0, $flat)) {
            foreach (array_keys($texts) as $cat) {
                $list = $flat[$cat] ?? null;
                if (!is_array($list)) {
                    continue;
                }
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

        foreach (array_keys($texts) as $cat) {
            $key = 'org_' . $cat;
            if (!$request->has($key)) {
                continue;
            }
            $list = $request->input($key);
            if (is_array($list) && array_key_exists(0, $list) && is_array($list[0])) {
                $k = 0;
                foreach ($list as $item) {
                    $add($cat, $item['name'] ?? null, $item['url'] ?? null, $k++);
                }
            } elseif (is_array($list) && isset($list['name']) && is_array($list['name'])) {
                $names = $list['name'];
                $urls  = is_array($list['url'] ?? null) ? $list['url'] : [];
                foreach ($names as $idx => $nm) {
                    $add($cat, $nm, $urls[$idx] ?? null, $idx);
                }
            } elseif (is_array($list)) {
                foreach ($list as $idx => $nm) {
                    $add($cat, is_string($nm) ? $nm : null, null, $idx);
                }
            }
        }

        foreach ($texts as $k => $arr) {
            $texts[$k] = $arr ? implode(' / ', array_values(array_unique($arr))) : null;
        }

        return ['rows' => $rows, 'text' => $texts];
    }

    /**
     * サイドバー：日程・成績
     */
    private function buildSidebarSchedule(Request $request): array
    {
        $out = [];
        $rows  = (array) $request->input('schedule', []);
        $files = $request->file('schedule_files', []);
        $keeps = (array) $request->input('schedule_keep', []);

        foreach ($rows as $i => $r) {
            $date  = trim((string) ($r['date'] ?? ''));
            $label = trim((string) ($r['label'] ?? ''));
            $url   = trim((string) ($r['url'] ?? ''));
            $sep   = !empty($r['separator']);

            $href = null;
            if (!$sep) {
                if ($url !== '') {
                    $href = preg_match('~^https?://~i', $url) ? $url : ('https://' . ltrim($url));
                } elseif (!empty($files[$i])) {
                    $href = $files[$i]->store('tournament_pdfs', 'public');
                } elseif (!empty($keeps[$i]['keep']) && !empty($keeps[$i]['href'])) {
                    $href = $keeps[$i]['href'];
                }
            }

            if (!$sep && $label === '' && $href === null) {
                continue;
            }

            $out[] = [
                'date'      => $date,
                'label'     => $label,
                'href'      => $href,
                'separator' => $sep,
            ];
        }

        $uniq = [];
        $dedup = [];
        foreach ($out as $r) {
            $k = ($r['date'] ?? '') . '|' . ($r['label'] ?? '') . '|' . ($r['href'] ?? '') . '|' . (!empty($r['separator']) ? '1' : '0');
            if (isset($uniq[$k])) {
                continue;
            }
            $uniq[$k] = true;
            $dedup[] = $r;
        }

        return $dedup;
    }

    private function buildAwardHighlights(Request $request): array
    {
        $out   = [];
        $rows  = (array) $request->input('awards', []);
        $files = $request->file('award_files', []);
        $keeps = (array) $request->input('awards_keep', []);

        foreach ($rows as $i => $r) {
            $type   = in_array(($r['type'] ?? ''), ['perfect', 'series800', 'split710'], true) ? $r['type'] : 'perfect';
            $player = trim((string) ($r['player'] ?? ''));
            $game   = trim((string) ($r['game'] ?? ''));
            $lane   = trim((string) ($r['lane'] ?? ''));
            $note   = trim((string) ($r['note'] ?? ''));
            $title  = trim((string) ($r['title'] ?? ''));

            $photo = null;
            if (!empty($files[$i])) {
                $photo = $files[$i]->store('tournament_awards', 'public');
            } elseif (!empty($keeps[$i]['photo'])) {
                $photo = $keeps[$i]['photo'];
            }

            if ($player === '' && $photo === null && $title === '') {
                continue;
            }

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

    private function buildGalleryAndResults(Request $request, Tournament $t = null): array
    {
        $gallery = [];
        $results = [];

        if (is_array($request->input('__keep_gallery'))) {
            foreach ($request->input('__keep_gallery') as $g) {
                if (!empty($g['photo'])) {
                    $gallery[] = ['photo' => $g['photo'], 'title' => $g['title'] ?? null];
                }
            }
        } elseif ($t && is_array($t->gallery_items)) {
            $gallery = $t->gallery_items;
        }

        if (is_array($request->input('__keep_results'))) {
            foreach ($request->input('__keep_results') as $r) {
                if (!empty($r['file'])) {
                    $results[] = ['file' => $r['file'], 'title' => $r['title'] ?? null];
                }
            }
        } elseif ($t && is_array($t->simple_result_pdfs)) {
            $results = $t->simple_result_pdfs;
        }

        if ($request->hasFile('gallery_files')) {
            $titles = (array) $request->input('gallery_titles', []);
            if (count($titles) === 1 && is_string($titles[0]) && str_contains($titles[0], "\n")) {
                $titles = array_map('trim', preg_split('/\r\n|\n|\r/u', $titles[0]));
            }
            foreach ($request->file('gallery_files') as $i => $f) {
                if (!$f) {
                    continue;
                }
                $path = $f->store('tournament_gallery', 'public');
                $gallery[] = ['photo' => $path, 'title' => $titles[$i] ?? null];
            }
        }

        if ($request->hasFile('result_pdfs')) {
            $titles = (array) $request->input('result_titles', []);
            if (count($titles) === 1 && is_string($titles[0]) && str_contains($titles[0], "\n")) {
                $titles = array_map('trim', preg_split('/\r\n|\n|\r/u', $titles[0]));
            }
            foreach ($request->file('result_pdfs') as $i => $f) {
                if (!$f) {
                    continue;
                }
                $path = $f->store('tournament_pdfs', 'public');
                $results[] = ['file' => $path, 'title' => $titles[$i] ?? null];
            }
        }

        return [$gallery, $results];
    }

    private function buildResultCards(Request $request, Tournament $t = null): array
    {
        $rows       = (array) $request->input('result_cards', []);
        $photoFiles = $request->file('result_card_photos', []);
        $pdfFiles   = $request->file('result_card_files', []);
        $keeps      = (array) $request->input('result_card_keep', []);

        $out = [];
        foreach ($rows as $i => $r) {
            $title  = trim((string) ($r['title'] ?? ''));
            $player = trim((string) ($r['player'] ?? ''));
            $balls  = trim((string) ($r['balls'] ?? ''));
            $note   = trim((string) ($r['note'] ?? ''));
            $url    = trim((string) ($r['url'] ?? ''));

            if ($url !== '' && !preg_match('~^https?://~i', $url)) {
                $url = 'https://' . ltrim($url);
            }

            $photos = [];
            if (!empty($keeps[$i]['photos']) && is_array($keeps[$i]['photos'])) {
                foreach ($keeps[$i]['photos'] as $p) {
                    if ($p !== null && $p !== '') {
                        $photos[] = $p;
                    }
                }
            }
            if (empty($keeps[$i]['photos']) && !empty($keeps[$i]['photo'])) {
                $photos[] = $keeps[$i]['photo'];
            }
            if (isset($photoFiles[$i])) {
                $slot = $photoFiles[$i];
                if (is_array($slot)) {
                    foreach ($slot as $pf) {
                        if (!$pf) {
                            continue;
                        }
                        $photos[] = $pf->store('tournament_results', 'public');
                    }
                } else {
                    if ($slot) {
                        $photos[] = $slot->store('tournament_results', 'public');
                    }
                }
            }

            $filePath = null;
            if (!empty($pdfFiles[$i])) {
                $filePath = $pdfFiles[$i]->store('tournament_pdfs', 'public');
            } elseif (!empty($keeps[$i]['file'])) {
                $filePath = $keeps[$i]['file'];
            }

            if ($title === '' && $player === '' && $balls === '' && $note === '' && $url === '' && !$photos && !$filePath) {
                continue;
            }

            $out[] = [
                'title'  => $title,
                'player' => $player,
                'balls'  => $balls,
                'note'   => $note,
                'url'    => $url,
                'photos' => $photos,
                'photo'  => $photos[0] ?? null,
                'file'   => $filePath,
            ];
        }

        return $out;
    }

    public function store(Request $request)
    {
        $validated = $this->validateAndNormalize($request);

        if ($request->hasFile('hero_image')) {
            $validated['hero_image_path'] = $request->file('hero_image')->store('posters', 'public');
        }
        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('title_logos', 'public');
        }
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }

        $posterPaths = [];
        if ($request->hasFile('posters')) {
            foreach ($request->file('posters') as $pf) {
                if ($pf) {
                    $posterPaths[] = $pf->store('posters', 'public');
                }
            }
        }
        if ($posterPaths) {
            $validated['poster_images'] = $posterPaths;
        }

        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('posters', 'public');
        }

        $validated['sidebar_schedule'] = $this->buildSidebarSchedule($request);
        $validated['award_highlights'] = $this->buildAwardHighlights($request);

        [$gallery, $results] = $this->buildGalleryAndResults($request, null);
        if ($gallery) {
            $validated['gallery_items'] = $gallery;
        }
        if ($results) {
            $validated['simple_result_pdfs'] = $results;
        }

        $cards = $this->buildResultCards($request, null);
        if ($cards) {
            $validated['result_cards'] = $cards;
        }

        $t = Tournament::create($validated);

        $org = $this->buildOrgRowsAndTexts($request);
        if (!empty($org['rows'])) {
            $t->organizations()->saveMany($org['rows']);
        }
        $t->fill([
            'host'            => $org['text']['host'],
            'special_sponsor' => $org['text']['special_sponsor'],
            'sponsor'         => $org['text']['sponsor'],
            'support'         => $org['text']['support'],
        ])->save();

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

        if ($request->hasFile('custom_files')) {
            $titles = (array) $request->input('custom_titles', []);
            $i = 0;
            foreach ($request->file('custom_files') as $file) {
                if (!$file) {
                    continue;
                }
                $title = $titles[$i] ?? '資料' . ($i + 1);
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
        $t = Tournament::with(['organizations', 'files'])->findOrFail($id);
        $validated = $this->validateAndNormalize($request);

        if ($request->hasFile('hero_image')) {
            $validated['hero_image_path'] = $request->file('hero_image')->store('posters', 'public');
        }
        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('title_logos', 'public');
        } else {
            $validated['title_logo_path'] = $t->title_logo_path;
        }
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('posters', 'public');
        }

        $posterPaths = $t->poster_images ?? [];
        if ($request->hasFile('posters')) {
            foreach ($request->file('posters') as $pf) {
                if ($pf) {
                    $posterPaths[] = $pf->store('posters', 'public');
                }
            }
            $validated['poster_images'] = $posterPaths;
        }

        if ($request->hasFile('title_logo')) {
            $validated['title_logo_path'] = $request->file('title_logo')->store('posters', 'public');
        } else {
            $validated['title_logo_path'] = $t->title_logo_path;
        }

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

        [$gallery, $results] = $this->buildGalleryAndResults($request, $t);
        $validated['gallery_items'] = $gallery;
        $validated['simple_result_pdfs'] = $results;

        if ($request->has('result_cards')) {
            $validated['result_cards'] = $this->buildResultCards($request, $t);
        } else {
            $validated['result_cards'] = $t->result_cards;
        }

        $t->update($validated);

        $t->organizations()->delete();
        $org = $this->buildOrgRowsAndTexts($request);
        if (!empty($org['rows'])) {
            $t->organizations()->saveMany($org['rows']);
        }
        $t->fill([
            'host'            => $org['text']['host'],
            'special_sponsor' => $org['text']['special_sponsor'],
            'sponsor'         => $org['text']['sponsor'],
            'support'         => $org['text']['support'],
        ])->save();

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

        if ($request->hasFile('custom_files')) {
            $titles = (array) $request->input('custom_titles', []);
            $i = 0;
            foreach ($request->file('custom_files') as $file) {
                if (!$file) {
                    continue;
                }
                $title = $titles[$i] ?? '資料' . ($i + 1);
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

    public function destroy($id)
    {
        $t = Tournament::with(['organizations', 'files', 'prizeDistributions', 'pointDistributions', 'entries'])
            ->findOrFail($id);

        DB::transaction(function () use ($t) {
            $paths = [];

            if ($t->image_path) {
                $paths[] = $t->image_path;
            }
            if ($t->hero_image_path) {
                $paths[] = $t->hero_image_path;
            }
            if ($t->title_logo_path) {
                $paths[] = $t->title_logo_path;
            }
            if (is_array($t->poster_images)) {
                foreach ($t->poster_images as $p) {
                    $paths[] = $p;
                }
            }
            if (is_array($t->gallery_items)) {
                foreach ($t->gallery_items as $gi) {
                    if (!empty($gi['photo'])) {
                        $paths[] = $gi['photo'];
                    }
                }
            }
            if (is_array($t->simple_result_pdfs)) {
                foreach ($t->simple_result_pdfs as $ri) {
                    if (!empty($ri['file'])) {
                        $paths[] = $ri['file'];
                    }
                }
            }
            foreach ($t->files as $f) {
                if ($f->file_path) {
                    $paths[] = $f->file_path;
                }
            }

            $paths = array_values(array_unique($paths));
            foreach ($paths as $p) {
                try {
                    Storage::disk('public')->delete($p);
                } catch (\Throwable $e) {
                }
            }

            $t->organizations()->delete();
            $t->files()->delete();
            if (method_exists($t, 'prizeDistributions')) {
                $t->prizeDistributions()->delete();
            }
            if (method_exists($t, 'pointDistributions')) {
                $t->pointDistributions()->delete();
            }
            if (method_exists($t, 'entries')) {
                $t->entries()->delete();
            }

            $t->delete();
        });

        return redirect()->route('tournaments.index')->with('success', '大会を削除しました。');
    }
}