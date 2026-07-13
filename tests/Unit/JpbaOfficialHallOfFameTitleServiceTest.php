<?php

namespace Tests\Unit;

use App\Services\JpbaOfficialHallOfFameTitleService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class JpbaOfficialHallOfFameTitleServiceTest extends TestCase
{
    public function test_it_discovers_only_hall_of_fame_profile_urls(): void
    {
        $html = <<<'HTML'
        <a href="HallofFame/IwakamiTaro.html">岩上太郎</a>
        <a href="HallofFame/IshiiRie.html">石井利枝</a>
        <a href="HallofFame.html">殿堂トップ</a>
        HTML;

        $urls = (new JpbaOfficialHallOfFameTitleService)->parseProfileUrls(
            $html,
            'https://www.jpba.or.jp/information/tournament/HallofFame.html'
        );

        $this->assertSame([
            'https://www.jpba.or.jp/information/tournament/HallofFame/IwakamiTaro.html',
            'https://www.jpba.or.jp/information/tournament/HallofFame/IshiiRie.html',
        ], $urls);
    }

    public function test_it_deduplicates_titles_and_joins_parenthesized_continuations(): void
    {
        $method = new ReflectionMethod(JpbaOfficialHallOfFameTitleService::class, 'mainTitles');
        $titles = $method->invoke(new JpbaOfficialHallOfFameTitleService, [
            'MainTitle',
            "1984\u{5E74} WIBC Queens Open",
            "1984\u{5E74} WIBC Queens Open",
            "1985\u{5E74} Championship",
            "\u{FF08}Sponsor Cup\u{FF09}",
            'PAGE TOP',
        ]);

        $this->assertSame([
            [
                'year' => 1984,
                'title_name' => 'WIBC Queens Open',
                'raw_text' => "1984\u{5E74} WIBC Queens Open",
            ],
            [
                'year' => 1985,
                'title_name' => "Championship \u{FF08}Sponsor Cup\u{FF09}",
                'raw_text' => "1985\u{5E74} Championship \u{FF08}Sponsor Cup\u{FF09}",
            ],
        ], $titles);
    }
}
