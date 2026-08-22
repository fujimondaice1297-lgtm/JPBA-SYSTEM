<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 編集可能な取材ページ本文だけを新サイト保存PDFへ切り替える。
     * source_url は移行元を示す非公開監査情報として維持する。
     */
    public function up(): void
    {
        $this->replaceLinks([
            'https://www.jpba1.jp/media/PDF/ComplianceRules_forMedia_230508.pdf'
                => '/documents/jpba/media-compliance-rules-2023-05-08.pdf',
            'https://www.jpba1.jp/media/PDF/ApplicationSheet_forMedia_2024.pdf'
                => '/documents/jpba/media-application-2024.pdf',
        ]);
    }

    public function down(): void
    {
        $this->replaceLinks([
            '/documents/jpba/media-compliance-rules-2023-05-08.pdf'
                => 'https://www.jpba1.jp/media/PDF/ComplianceRules_forMedia_230508.pdf',
            '/documents/jpba/media-application-2024.pdf'
                => 'https://www.jpba1.jp/media/PDF/ApplicationSheet_forMedia_2024.pdf',
        ]);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function replaceLinks(array $replacements): void
    {
        $body = DB::table('managed_public_pages')
            ->where('slug', 'media')
            ->value('body_html');

        if (! is_string($body) || $body === '') {
            return;
        }

        $updatedBody = str_replace(array_keys($replacements), array_values($replacements), $body);

        if ($updatedBody === $body) {
            return;
        }

        DB::table('managed_public_pages')
            ->where('slug', 'media')
            ->update([
                'body_html' => $updatedBody,
                'updated_at' => now(),
            ]);
    }
};
