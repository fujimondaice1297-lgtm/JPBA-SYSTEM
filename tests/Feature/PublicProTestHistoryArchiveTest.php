<?php

test('the public pro test page links every archived year', function () {
    $this->get(route('public.protest'))
        ->assertOk()
        ->assertSee('過去のプロテスト結果')
        ->assertSee('2008年度')
        ->assertSee('2026年度')
        ->assertSee('開催中止');
});

test('an archived pro test year is shown with internal result documents', function () {
    $this->get(route('public.pro_tests.history', ['year' => 2018]))
        ->assertOk()
        ->assertSee('2018年度 プロテスト結果')
        ->assertSee('第1次 東日本 1日目')
        ->assertSee('最終結果・合格者')
        ->assertSee('/documents/jpba/protest/2018/Result/2018test1_E.pdf', false)
        ->assertDontSee('jpba.or.jp', false)
        ->assertDontSee('jpba1.jp', false);
});

test('the cancelled 2020 pro test is explained without result documents', function () {
    $this->get(route('public.pro_tests.history', ['year' => 2020]))
        ->assertOk()
        ->assertSee('2020年度 プロテスト結果')
        ->assertSee('2020年度のプロテストは中止')
        ->assertDontSee('/documents/jpba/protest/2020/', false);
});

test('unknown pro test archive years return not found', function () {
    $this->get(route('public.pro_tests.history', ['year' => 2007]))->assertNotFound();
});

test('all archived pro test pdfs are stored inside the new site', function () {
    $documents = collect(config('pro_test_history.years'))
        ->flatMap(fn (array $history): array => $history['documents']);

    expect($documents)->toHaveCount(239);

    foreach ($documents as $document) {
        expect($document['url'])
            ->toStartWith('/documents/jpba/protest/')
            ->not->toContain('jpba.or.jp')
            ->not->toContain('jpba1.jp');

        $path = public_path(ltrim($document['url'], '/'));
        expect($path)->toBeFile();

        $handle = fopen($path, 'rb');
        expect($handle)->not->toBeFalse();
        expect(fread($handle, 5))->toBe('%PDF-');
        fclose($handle);
    }
});
