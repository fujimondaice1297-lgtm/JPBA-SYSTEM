<?php

namespace App\Console\Commands;

use App\Models\Information;
use App\Models\ManagedPublicPage;
use App\Models\ProBowler;
use App\Models\ProTestEvent;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Throwable;

class RunPublicSiteParityAudit extends Command
{
    protected $signature = 'public:parity-audit
        {--json : Output audit result as JSON}
        {--dynamic-only : Audit only representative data-driven detail pages}';

    protected $description = 'Audit public JPBA pages for current-site navigation, footer, images, PDF links, and external links.';

    public function handle(Kernel $kernel): int
    {
        $config = config('jpba_public', []);
        $globalRequiredLabels = $this->globalRequiredLabels($config);
        $pages = array_merge(
            $this->option('dynamic-only') ? [] : $this->publicPages(),
            $this->dynamicPublicPages()
        );
        $results = [];

        foreach ($pages as $page) {
            $results[] = $this->auditPage($kernel, $page, $globalRequiredLabels);
        }

        $failed = collect($results)->contains(fn (array $row) => ($row['status'] ?? '') === 'FAIL');

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['page', 'path', 'http', 'status', 'missing_labels', 'images', 'pdf_links', 'external_links', 'internal_links', 'missing_assets'],
                array_map(fn (array $row) => [
                    $row['page'] ?? '',
                    $row['path'] ?? '',
                    $row['http_status'] ?? '',
                    $row['status'] ?? '',
                    implode(', ', $row['missing_labels'] ?? []),
                    $row['image_count'] ?? 0,
                    $row['pdf_link_count'] ?? 0,
                    $row['external_link_count'] ?? 0,
                    $row['internal_link_count'] ?? 0,
                    $row['missing_asset_count'] ?? 0,
                ], $results)
            );
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function publicPages(): array
    {
        return [
            ['page' => 'point-distribution', 'path' => '/rankings/point-distribution', 'required' => ['JPBAポイント配分表', '男子（96名）', '女子（72名）', 'シーズントライアル（8名）']],
            ['page' => 'women-tournament-priority', 'path' => '/rankings/women-tournament-priority?year=2026&period=lower', 'required' => ['2026年度下半期女子トーナメント出場優先順位', '239名', '下半期出場優先順位決定戦']],
            ['page' => 'home', 'path' => '/', 'required' => ['TOURNAMENT', 'INFORMATION', '公式PDF', '関連チャンネル', '会員・関係者', '法人・個人賛助会員', '公式SNS', '関連団体', '2026 JPBAトーナメント予定表', 'JPBAツアー ご観戦時のご案内', 'ウレタンボールの使用規制について', 'JPBA LIVEチャンネル', 'io.LEAGUEチャンネル', 'io.LEAGUE Official Website']],
            ['page' => 'about', 'path' => '/about', 'required' => ['JPBAについて', '協会概要', '事業']],
            ['page' => 'schedule', 'path' => '/schedule', 'required' => ['スケジュール']],
            ['page' => 'players', 'path' => '/players', 'required' => ['選手データ']],
            ['page' => 'tournaments', 'path' => '/tournament', 'required' => ['トーナメント']],
            ['page' => 'live-results', 'path' => '/tournament/live-results', 'required' => ['速報・成績', '男子ポイントランキング', '女子賞金ランキング', 'JPBAポイント配分表', 'ST年間ポイントランキング', 'STチャンピオンズ優先出場一覧']],
            ['page' => 'official-current-ranking', 'path' => '/rankings/current?year=2026&gender=M&type=points', 'required' => ['2026年 男子ポイントランキング', '獲得賞金']],
            ['page' => 'season-trial-ranking', 'path' => '/rankings/season-trial?year=2026', 'required' => ['シーズントライアル年間ポイントランキング', '優先出場一覧']],
            ['page' => 'season-trial-priority', 'path' => '/rankings/season-trial/championship-priority?year=2026', 'required' => ['STチャンピオンズ優先出場一覧', 'ST年間ポイント']],
            ['page' => 'instructors', 'path' => '/instructor', 'required' => ['インストラクター']],
            ['page' => 'protest', 'path' => '/protest', 'required' => ['プロテスト']],
            ['page' => 'topics', 'path' => '/topics', 'required' => ['トピックス']],
            ['page' => 'support', 'path' => '/support', 'required' => ['法人・個人賛助会員', '法人賛助会員のご案内', '個人賛助会員のご案内']],
            ['page' => 'support-corporate', 'path' => '/pages/support-corporate', 'required' => ['法人賛助会員のご案内', '法人賛助会員一覧']],
            ['page' => 'support-individual', 'path' => '/pages/support-individual', 'required' => ['個人賛助会員のご案内', '個人賛助会員申込書']],
            ['page' => 'instructor-flow', 'path' => '/pages/instructor-flow', 'required' => ['インストラクター資格取得までの流れ']],
            ['page' => 'instructor-plan', 'path' => '/pages/instructor-plan', 'required' => ['インストラクター講習会年間計画']],
            ['page' => 'instructor-school-about', 'path' => '/pages/instructor-school-about', 'required' => ['JPBA公認ボウリングスクールについて', '開講できるインストラクター']],
            ['page' => 'instructor-signage', 'path' => '/pages/instructor-signage', 'required' => ['インストラクター ステッカー・ワッペン', 'ボウリング場掲示用ステッカー']],
            ['page' => 'contact', 'path' => '/contact', 'required' => ['お問い合わせ']],
            ['page' => 'media', 'path' => '/media', 'required' => ['取材のお申込み']],
            ['page' => 'commerce', 'path' => '/commerce', 'required' => ['特定商取引法に基づく表記']],
            ['page' => 'privacy', 'path' => '/privacy', 'required' => ['プライバシーポリシー']],
            ['page' => 'information-index', 'path' => '/info', 'required' => ['INFORMATION', 'カテゴリ']],
        ];
    }

    /**
     * Public detail pages are selected from the current database so the audit
     * follows real route-model bindings instead of relying on fixed IDs.
     *
     * @return array<int,array<string,mixed>>
     */
    private function dynamicPublicPages(): array
    {
        $pages = [];

        $player = ProBowler::query()
            ->where('is_visible', true)
            ->whereNotNull('name_kanji')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
        if ($player) {
            $pages[] = [
                'page' => 'player-profile:'.$player->id,
                'path' => '/players/'.$player->id,
                'required' => ['選手プロフィール', (string) $player->name_kanji, '公式戦記録', '出場大会'],
            ];
        }

        $tournament = Tournament::query()
            ->whereIn('setup_status', ['in_progress', 'provisional', 'final', 'archived', 'completed'])
            ->where(function ($query): void {
                $query->whereHas('gameScores')->orWhereHas('officialResults');
            })
            ->orderByRaw('start_date desc nulls last')
            ->orderByDesc('id')
            ->first();
        if ($tournament) {
            $pages[] = [
                'page' => 'tournament-detail:'.$tournament->id,
                'path' => '/tournament/'.$tournament->id,
                'required' => [(string) $tournament->name, '資料・速報・成績'],
            ];
            $pages[] = [
                'page' => 'tournament-live:'.$tournament->id,
                'path' => '/tournament/'.$tournament->id.'/live',
                'required' => [(string) $tournament->name, '速報', '表示を切り替える'],
            ];

            if ($tournament->officialResults()->exists()) {
                $pages[] = [
                    'page' => 'tournament-results:'.$tournament->id,
                    'path' => '/tournament/'.$tournament->id.'/results',
                    'required' => [(string) $tournament->name, '全成績', '最終成績'],
                ];
            }

            $entry = TournamentEntry::query()
                ->where('tournament_id', $tournament->id)
                ->where('status', 'entry')
                ->whereHas('bowler')
                ->with('bowler')
                ->withCount('balls')
                ->orderByDesc('balls_count')
                ->orderBy('id')
                ->first();
            if ($entry) {
                $pages[] = [
                    'page' => 'tournament-entries:'.$tournament->id,
                    'path' => '/tournament/'.$tournament->id.'/entries',
                    'required' => [(string) $tournament->name, 'エントリープロ'],
                ];
                $pages[] = [
                    'page' => 'tournament-entry-balls:'.$entry->id,
                    'path' => '/tournament-entries/'.$entry->id.'/registered-balls?public=1',
                    'required' => [(string) $entry->bowler->name_kanji, '大会登録ボール'],
                ];
            }
        }

        $information = Information::query()
            ->public()
            ->active()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
        if ($information) {
            $pages[] = [
                'page' => 'information-detail:'.$information->id,
                'path' => '/info/'.$information->id,
                'required' => ['お知らせ 詳細', (string) $information->title],
            ];
        }

        $managedPage = ManagedPublicPage::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
        if ($managedPage) {
            $pages[] = [
                'page' => 'managed-page:'.$managedPage->id,
                'path' => '/pages/'.$managedPage->slug,
                'required' => [(string) $managedPage->title],
            ];
        }

        $proTest = ProTestEvent::query()
            ->publiclyVisible()
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->first();
        if ($proTest) {
            $pages[] = [
                'page' => 'pro-test-event:'.$proTest->id,
                'path' => '/protest/results/'.$proTest->id,
                'required' => [(string) $proTest->name, '速報・結果', '年齢・生年月日・住所・連絡先を公開していません'],
            ];

            $session = $proTest->sessions()
                ->whereNotNull('published_at')
                ->whereHas('publications')
                ->orderByDesc('sort_order')
                ->orderByDesc('id')
                ->first();
            if ($session) {
                $pages[] = [
                    'page' => 'pro-test-session:'.$session->id,
                    'path' => '/protest/results/'.$proTest->id.'/sessions/'.$session->id,
                    'required' => [(string) $proTest->name, $session->display_name, '年齢・生年月日・住所・連絡先は公開していません'],
                ];
            }
        }

        return $pages;
    }

    /**
     * @param  array<string,mixed>  $config
     * @return array<int,string>
     */
    private function globalRequiredLabels(array $config): array
    {
        $labels = [
            '公益社団法人 日本プロボウリング協会',
            'Japan Professional Bowling Association',
            'INFORMATION',
        ];

        foreach (['primary_nav', 'utility_links', 'footer_links'] as $key) {
            foreach ((array) ($config[$key] ?? []) as $link) {
                $label = trim((string) ($link['label'] ?? ''));
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param  array<string,mixed>  $page
     * @param  array<int,string>  $globalRequiredLabels
     * @return array<string,mixed>
     */
    private function auditPage(Kernel $kernel, array $page, array $globalRequiredLabels): array
    {
        $path = (string) ($page['path'] ?? '/');
        $request = Request::create($path, 'GET', [], [], [], [
            'HTTP_HOST' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);

        try {
            $response = $kernel->handle($request);
            $content = (string) $response->getContent();
            $kernel->terminate($request, $response);
        } catch (Throwable $e) {
            return [
                'page' => $page['page'] ?? $path,
                'path' => $path,
                'http_status' => 0,
                'status' => 'FAIL',
                'missing_labels' => [],
                'image_count' => 0,
                'pdf_link_count' => 0,
                'external_link_count' => 0,
                'internal_link_count' => 0,
                'missing_asset_count' => 0,
                'message' => $e->getMessage(),
            ];
        }

        $requiredLabels = array_values(array_unique(array_merge(
            $globalRequiredLabels,
            (array) ($page['required'] ?? [])
        )));

        $missingLabels = [];
        foreach ($requiredLabels as $label) {
            if (! $this->containsText($content, (string) $label)) {
                $missingLabels[] = (string) $label;
            }
        }

        $dom = $this->loadDom($content);
        $links = $this->extractAttributeValues($dom, 'a', 'href');
        $images = $this->extractAttributeValues($dom, 'img', 'src');
        $localAssetPaths = array_merge($images, $this->localAssetLinks($links));
        $missingAssets = array_values(array_filter(
            $localAssetPaths,
            fn (string $url) => ! $this->localAssetExists($kernel, $url)
        ));

        $httpStatus = (int) $response->getStatusCode();
        $status = ($httpStatus >= 200
            && $httpStatus < 400
            && empty($missingLabels)
            && empty($missingAssets)) ? 'OK' : 'FAIL';

        return [
            'page' => $page['page'] ?? $path,
            'path' => $path,
            'http_status' => $httpStatus,
            'status' => $status,
            'missing_labels' => $missingLabels,
            'image_count' => count($images),
            'pdf_link_count' => count(array_filter($links, fn (string $href) => str_contains(strtolower($href), '.pdf'))),
            'external_link_count' => count(array_filter($links, fn (string $href) => $this->isExternalUrl($href))),
            'internal_link_count' => count(array_filter($links, fn (string $href) => ! $this->isExternalUrl($href))),
            'missing_asset_count' => count($missingAssets),
            'missing_assets' => $missingAssets,
        ];
    }

    private function containsText(string $html, string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return true;
        }

        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $plain = preg_replace('/\s+/u', '', $plain) ?: $plain;
        $needle = preg_replace('/\s+/u', '', $label) ?: $label;

        return str_contains($plain, $needle);
    }

    private function loadDom(string $html): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @return array<int,string>
     */
    private function extractAttributeValues(DOMDocument $dom, string $tag, string $attribute): array
    {
        $values = [];
        foreach ($dom->getElementsByTagName($tag) as $node) {
            $value = trim((string) $node->getAttribute($attribute));
            if ($value !== '' && $value !== '#') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<int,string>  $links
     * @return array<int,string>
     */
    private function localAssetLinks(array $links): array
    {
        return array_values(array_filter($links, function (string $href): bool {
            $lower = strtolower($href);

            return str_contains($lower, '/storage/')
                || str_contains($lower, '/images/')
                || str_contains($lower, '/assets/')
                || str_ends_with($lower, '.pdf');
        }));
    }

    private function localAssetExists(Kernel $kernel, string $url): bool
    {
        if ($this->isExternalUrl($url)) {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return true;
        }

        if (is_file(public_path($path))) {
            return true;
        }

        if (str_starts_with($path, 'storage/')) {
            if (is_file(storage_path('app/public/'.substr($path, strlen('storage/'))))) {
                return true;
            }
        }

        $request = Request::create($url, 'GET', [], [], [], [
            'HTTP_HOST' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ]);

        try {
            $response = $kernel->handle($request);
            $status = (int) $response->getStatusCode();
            $kernel->terminate($request, $response);

            return $status >= 200 && $status < 400;
        } catch (Throwable) {
            return false;
        }
    }

    private function isExternalUrl(string $href): bool
    {
        if (! preg_match('/^https?:\/\//i', $href)) {
            return false;
        }

        $host = parse_url($href, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return $host !== null && strcasecmp($host, $appHost) !== 0;
    }
}
