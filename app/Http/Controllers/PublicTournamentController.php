<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicTournamentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'type' => trim((string) $request->query('type', '')),
            'year' => trim((string) $request->query('year', '')),
            'month' => trim((string) $request->query('month', '')),
            'region' => trim((string) $request->query('region', '')),
        ];

        $query = Tournament::query()
            ->with(['files' => fn ($q) => $q->where('visibility', 'public')->orderBy('sort_order'), 'venue']);

        if (in_array($filters['type'], ['official', 'approved', 'other'], true)) {
            $query->where('official_type', $filters['type']);
        }

        if ($filters['year'] !== '' && ctype_digit($filters['year'])) {
            $year = (int) $filters['year'];
            $query->where(function ($q) use ($year) {
                $q->where('year', $year)
                    ->orWhereYear('start_date', $year);
            });
        }

        if ($filters['month'] !== '' && ctype_digit($filters['month'])) {
            $month = (int) $filters['month'];
            if ($month >= 1 && $month <= 12) {
                $query->where(function ($q) use ($month) {
                    $q->whereMonth('start_date', $month)
                        ->orWhereMonth('end_date', $month);
                });
            }
        }

        $this->applyRegionFilter($query, $filters['region']);

        $tournaments = $query
            ->orderByRaw('start_date desc nulls last')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('public.tournaments.index', [
            'publicConfig' => config('jpba_public', []),
            'filters' => $filters,
            'tournaments' => $tournaments,
            'years' => $this->availableYears(),
            'typeOptions' => $this->typeOptions(),
            'regionOptions' => $this->regionOptions(),
        ]);
    }

    public function show(Tournament $tournament): View
    {
        $tournament->load([
            'files' => fn ($q) => $q->where('visibility', 'public')->orderBy('sort_order'),
            'organizations',
            'venue',
        ]);

        return view('public.tournaments.show', [
            'publicConfig' => config('jpba_public', []),
            'tournament' => $tournament,
            'fileLinks' => $this->fileLinks($tournament),
            'scheduleLinks' => $this->scheduleLinks($tournament),
            'resultCards' => $this->resultCards($tournament),
            'resultRows' => $this->resultRows($tournament),
        ]);
    }

    private function availableYears(): array
    {
        return Tournament::query()
            ->selectRaw('coalesce(year, extract(year from start_date)::int) as display_year')
            ->where(function ($q) {
                $q->whereNotNull('year')->orWhereNotNull('start_date');
            })
            ->distinct()
            ->orderByDesc('display_year')
            ->pluck('display_year')
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();
    }

    private function fileLinks(Tournament $tournament): array
    {
        return $tournament->files
            ->map(fn ($file) => [
                'label' => $file->title ?: $this->fileTypeLabel((string) $file->type),
                'url' => $this->storageUrl((string) $file->file_path),
                'type' => (string) $file->type,
            ])
            ->values()
            ->all();
    }

    private function scheduleLinks(Tournament $tournament): array
    {
        return collect($tournament->sidebar_schedule ?? [])
            ->map(function ($row) {
                $label = trim((string) ($row['label'] ?? ($row['title'] ?? '')));
                $url = trim((string) ($row['href'] ?? ($row['url'] ?? ($row['file'] ?? ''))));
                $date = trim((string) ($row['date'] ?? ''));

                if ($label === '' && $url === '') {
                    return null;
                }

                return [
                    'date' => $date,
                    'label' => $label !== '' ? $label : '関連資料',
                    'url' => $this->normalizeUrl($url),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resultCards(Tournament $tournament): array
    {
        $cards = collect($tournament->result_cards ?? [])
            ->map(function ($row) {
                $title = trim((string) ($row['title'] ?? ''));
                $player = trim((string) ($row['player'] ?? ''));
                $url = trim((string) ($row['url'] ?? ''));
                $file = trim((string) ($row['file'] ?? ''));

                if ($title === '' && $player === '' && $url === '' && $file === '') {
                    return null;
                }

                return [
                    'title' => $title,
                    'player' => $player,
                    'note' => trim((string) ($row['note'] ?? '')),
                    'balls' => trim((string) ($row['balls'] ?? '')),
                    'url' => $this->normalizeUrl($url !== '' ? $url : $file),
                ];
            })
            ->filter();

        $simplePdfs = collect($tournament->simple_result_pdfs ?? [])
            ->map(function ($row) {
                $title = trim((string) ($row['title'] ?? ($row['label'] ?? '成績PDF')));
                $url = trim((string) ($row['url'] ?? ($row['file'] ?? '')));

                return $url !== ''
                    ? ['title' => $title, 'player' => '', 'note' => '', 'balls' => '', 'url' => $this->normalizeUrl($url)]
                    : null;
            })
            ->filter();

        return $cards->merge($simplePdfs)->values()->all();
    }

    private function resultRows(Tournament $tournament)
    {
        return DB::table('tournament_results as tr')
            ->leftJoin('pro_bowlers as pb', 'pb.id', '=', 'tr.pro_bowler_id')
            ->where('tr.tournament_id', $tournament->id)
            ->orderByRaw('tr.ranking asc nulls last')
            ->orderByDesc('tr.total_pin')
            ->limit(10)
            ->get([
                'tr.ranking',
                'tr.pro_bowler_license_no',
                'tr.total_pin',
                'tr.games',
                'tr.average',
                'tr.points',
                'tr.amateur_name',
                'pb.name_kanji as pro_name',
            ]);
    }

    private function applyRegionFilter($query, string $region): void
    {
        $options = $this->regionOptions();
        if (!isset($options[$region])) {
            return;
        }

        $keywords = $options[$region]['keywords'];
        $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhere('venue_address', 'like', "%{$keyword}%")
                    ->orWhere('venue_name', 'like', "%{$keyword}%");
            }
        });
    }

    private function typeOptions(): array
    {
        return [
            'official' => '公認トーナメント',
            'approved' => '承認イベント',
            'other' => 'その他',
        ];
    }

    private function regionOptions(): array
    {
        return [
            'hokkaido_tohoku' => ['label' => '北海道・東北', 'keywords' => ['北海道', '青森', '岩手', '宮城', '秋田', '山形', '福島']],
            'kanto' => ['label' => '関東', 'keywords' => ['茨城', '栃木', '群馬', '埼玉', '千葉', '東京', '神奈川']],
            'tokai_hokushinetsu' => ['label' => '東海・北信越', 'keywords' => ['新潟', '富山', '石川', '福井', '山梨', '長野', '岐阜', '静岡', '愛知', '三重']],
            'kinki' => ['label' => '近畿', 'keywords' => ['滋賀', '京都', '大阪', '兵庫', '奈良', '和歌山']],
            'chugoku_shikoku' => ['label' => '中国・四国', 'keywords' => ['鳥取', '島根', '岡山', '広島', '山口', '徳島', '香川', '愛媛', '高知']],
            'kyushu_okinawa' => ['label' => '九州･沖縄', 'keywords' => ['福岡', '佐賀', '長崎', '熊本', '大分', '宮崎', '鹿児島', '沖縄']],
        ];
    }

    private function fileTypeLabel(string $type): string
    {
        return match ($type) {
            'outline_public' => '大会要項',
            'outline_player' => '選手向け要項',
            'oil_pattern' => 'オイルパターン',
            default => '関連資料',
        };
    }

    private function storageUrl(string $path): string
    {
        return $this->normalizeUrl($path);
    }

    private function normalizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '#';
        }

        if (preg_match('~^https?://~i', $value) || str_starts_with($value, '/')) {
            return $value;
        }

        return asset('storage/' . ltrim($value, '/'));
    }
}
