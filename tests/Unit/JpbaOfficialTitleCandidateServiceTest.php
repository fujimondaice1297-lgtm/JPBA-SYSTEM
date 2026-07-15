<?php

namespace Tests\Unit;

use App\Services\JpbaOfficialTitleCandidateService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class JpbaOfficialTitleCandidateServiceTest extends TestCase
{
    public function test_it_excludes_the_2018_three_organization_final_from_title_candidates(): void
    {
        $service = new JpbaOfficialTitleCandidateService;
        $html = <<<'HTML'
        <html><head><title>ROUND1 GRAND CHAMPIONSHIP BOWLING 2018 三団体グランドチャンピオン大会</title></head>
        <body><h1>ROUND1 GRAND CHAMPIONSHIP BOWLING 2018 三団体グランドチャンピオン大会</h1></body></html>
        HTML;

        $this->assertSame([], $service->parseCandidates(
            $html,
            'https://www.jpba.or.jp/information/tournament/tournament2018/01ROUND1/Final/ROUND1GCB2018_Final.html'
        ));
    }

    public function test_it_keeps_the_jpba_final_as_an_official_title_event(): void
    {
        $method = new ReflectionMethod(JpbaOfficialTitleCandidateService::class, 'isExplicitNonTitleTournament');
        $service = new JpbaOfficialTitleCandidateService;

        $this->assertFalse($method->invoke(
            $service,
            'https://www.jpba.or.jp/information/tournament/tournament2018/01ROUND1/JPBA/ROUND1GCB2018_JPBA.html',
            'ROUND1 GRAND CHAMPIONSHIP BOWLING 2018 JPBA決勝大会'
        ));
    }
}
