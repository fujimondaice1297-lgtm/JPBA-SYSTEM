<?php

namespace App\Services;

class ManagedPublicPageSanitizer
{
    private const ALLOWED_TAGS = '<p><br><div><h2><h3><h4><ul><ol><li><strong><b><em><i><u><a><table><thead><tbody><tr><th><td><blockquote><hr>';

    public function sanitize(?string $html): string
    {
        $html = strip_tags((string) $html, self::ALLOWED_TAGS);

        // 管理画面の貼り付け内容からイベント属性・危険なURLだけを除外する。
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;
        $html = preg_replace('/\s+(style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*(["\'])\s*(?:javascript|data):[^"\']*\1/iu', 'href="#"', $html) ?? $html;

        return trim($html);
    }
}
