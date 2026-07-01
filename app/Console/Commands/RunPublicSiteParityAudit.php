<?php

namespace App\Console\Commands;

use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

class RunPublicSiteParityAudit extends Command
{
    protected $signature = 'public:parity-audit {--json : Output audit result as JSON}';

    protected $description = 'Audit public JPBA pages for current-site navigation, footer, images, PDF links, and external links.';

    public function handle(Kernel $kernel): int
    {
        $config = config('jpba_public', []);
        $globalRequiredLabels = $this->globalRequiredLabels($config);
        $pages = $this->publicPages();
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
            ['page' => 'home', 'path' => '/', 'required' => ['TOURNAMENT', 'INFORMATION', '公式PDF', '関連チャンネル', '会員・関係者', '2026 JPBAトーナメント予定表', 'JPBAツアー ご観戦時のご案内', 'ウレタンボールの使用規制について', 'JPBA LIVEチャンネル', 'io.LEAGUEチャンネル', 'io.LEAGUE Official Website']],
            ['page' => 'about', 'path' => '/about', 'required' => ['JPBAについて', '協会概要', '事業']],
            ['page' => 'schedule', 'path' => '/schedule', 'required' => ['スケジュール']],
            ['page' => 'players', 'path' => '/players', 'required' => ['選手データ']],
            ['page' => 'tournaments', 'path' => '/tournament', 'required' => ['トーナメント']],
            ['page' => 'instructors', 'path' => '/instructor', 'required' => ['インストラクター']],
            ['page' => 'protest', 'path' => '/protest', 'required' => ['プロテスト']],
            ['page' => 'topics', 'path' => '/topics', 'required' => ['トピックス']],
            ['page' => 'contact', 'path' => '/contact', 'required' => ['お問い合わせ']],
            ['page' => 'media', 'path' => '/media', 'required' => ['取材のお申込み']],
            ['page' => 'commerce', 'path' => '/commerce', 'required' => ['特定商取引法に基づく表記']],
            ['page' => 'privacy', 'path' => '/privacy', 'required' => ['プライバシーポリシー']],
        ];
    }

    /**
     * @param array<string,mixed> $config
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
     * @param array<string,mixed> $page
     * @param array<int,string> $globalRequiredLabels
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
            if (!$this->containsText($content, (string) $label)) {
                $missingLabels[] = (string) $label;
            }
        }

        $dom = $this->loadDom($content);
        $links = $this->extractAttributeValues($dom, 'a', 'href');
        $images = $this->extractAttributeValues($dom, 'img', 'src');
        $localAssetPaths = array_merge($images, $this->localAssetLinks($links));
        $missingAssets = array_values(array_filter($localAssetPaths, fn (string $url) => !$this->localAssetExists($url)));

        $httpStatus = (int) $response->getStatusCode();
        $status = ($httpStatus >= 200 && $httpStatus < 400 && empty($missingLabels)) ? 'OK' : 'FAIL';

        return [
            'page' => $page['page'] ?? $path,
            'path' => $path,
            'http_status' => $httpStatus,
            'status' => $status,
            'missing_labels' => $missingLabels,
            'image_count' => count($images),
            'pdf_link_count' => count(array_filter($links, fn (string $href) => str_contains(strtolower($href), '.pdf'))),
            'external_link_count' => count(array_filter($links, fn (string $href) => $this->isExternalUrl($href))),
            'internal_link_count' => count(array_filter($links, fn (string $href) => !$this->isExternalUrl($href))),
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
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
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
     * @param array<int,string> $links
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

    private function localAssetExists(string $url): bool
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
            return is_file(storage_path('app/public/' . substr($path, strlen('storage/'))));
        }

        return false;
    }

    private function isExternalUrl(string $href): bool
    {
        if (!preg_match('/^https?:\/\//i', $href)) {
            return false;
        }

        $host = parse_url($href, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return $host !== null && strcasecmp($host, $appHost) !== 0;
    }
}
