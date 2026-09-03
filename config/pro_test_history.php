<?php

$document = static fn (int $year, string $group, string $label, string $path): array => [
    'group' => $group,
    'label' => $label,
    'url' => "/documents/jpba/protest/{$year}/{$path}",
];

$years = [];

for ($year = 2008; $year <= 2018; $year++) {
    $maleGeneration = $year - 1961;
    $femaleGeneration = $year - 1967;
    $documents = [];

    foreach ([
        1 => '男子15G・女子12G',
        2 => '男子30G・女子24G',
        3 => '男子45G・女子36G',
        4 => '男子60G・女子48G',
    ] as $day => $games) {
        foreach (['E' => '東日本', 'W' => '西日本'] as $areaCode => $areaLabel) {
            $documents[] = $document(
                $year,
                'first',
                "第1次 {$areaLabel} {$day}日目（{$games}）",
                "Result/{$year}test{$day}_{$areaCode}.pdf"
            );
        }
    }

    foreach ([
        5 => ['1日目', '男子15G・女子12G'],
        6 => ['2日目', '男子30G・女子24G'],
        7 => ['3日目', '男子45G・女子36G'],
        8 => ['4日目', '男子60G・女子48G'],
    ] as $fileNumber => [$dayLabel, $games]) {
        $documents[] = $document(
            $year,
            'second',
            "第2次 {$dayLabel}（{$games}）",
            "Result/{$year}test{$fileNumber}.pdf"
        );
    }

    $finalPath = $year <= 2013
        ? "PDF/{$year}_finallist.pdf"
        : "Result/{$year}test9.pdf";
    $documents[] = $document($year, 'final', '最終結果・合格者', $finalPath);

    $years[$year] = [
        'year' => $year,
        'male_generation' => "第{$maleGeneration}期",
        'female_generation' => "第{$femaleGeneration}期",
        'held' => true,
        'note' => null,
        'documents' => $documents,
    ];
}

$years[2019] = [
    'year' => 2019,
    'male_generation' => '第58期',
    'female_generation' => '第52期',
    'held' => true,
    'note' => null,
    'documents' => array_merge(
        array_map(
            static fn (array $row): array => $document(2019, 'first', $row[0], $row[1]),
            [
                ['第1次 東日本 1日目（男子15G・女子12G）', 'result/primary/day1st_East.pdf'],
                ['第1次 西日本 1日目（男子15G・女子12G）', 'result/primary/day1st_West.pdf'],
                ['第1次 東日本 2日目（男子30G・女子24G）', 'result/primary/day2nd_East.pdf'],
                ['第1次 西日本 2日目（男子30G・女子24G）', 'result/primary/day2nd_West.pdf'],
                ['第1次 東日本 3日目（男子45G・女子36G）', 'result/primary/day3rd_East.pdf'],
                ['第1次 西日本 3日目（男子45G・女子36G）', 'result/primary/day3rd_West.pdf'],
                ['第1次 東日本 4日目（男子60G・女子48G）', 'result/primary/day4th_East.pdf'],
                ['第1次 西日本 4日目（男子60G・女子48G）', 'result/primary/day4th_West.pdf'],
            ]
        ),
        array_map(
            static fn (array $row): array => $document(2019, 'second', $row[0], $row[1]),
            [
                ['第2次 1日目（男子15G・女子12G）', 'result/secondary/day1st.pdf'],
                ['第2次 2日目（男子30G・女子24G）', 'result/secondary/day2nd.pdf'],
                ['第2次 3日目（男子45G・女子36G）', 'result/secondary/day3rd.pdf'],
                ['第2次 4日目（男子60G・女子48G）', 'result/secondary/day4th.pdf'],
            ]
        ),
        [$document(2019, 'final', '最終結果・合格者', 'result/M58W52.pdf')]
    ),
];

$years[2020] = [
    'year' => 2020,
    'male_generation' => null,
    'female_generation' => null,
    'held' => false,
    'note' => '新型コロナウイルス感染症の影響により、2020年度のプロテストは中止となりました。第59期男子・第53期女子は2021年度に実施されています。',
    'documents' => [],
];

foreach ([2021, 2022, 2023] as $year) {
    $maleGeneration = $year - 1962;
    $femaleGeneration = $year - 1968;
    $documents = [];
    $paths = match ($year) {
        2021 => [
            'first_east' => 'Result/1st_East_M',
            'first_west' => 'Result/1st_West_M',
            'second' => 'Result/2nd_M',
            'final' => 'Result/2021_Newcomer.pdf',
        ],
        2022 => [
            'first_east' => 'Result/1st_East_M',
            'first_west' => 'Result/1st_West_M',
            'second' => 'Result/2nd_M',
            'final' => 'PDF/2022_Protest.pdf',
        ],
        default => [
            'first_east' => 'Result/1st/1st_East_M',
            'first_west' => 'Result/1st/1st_West_M',
            'second' => 'Result/2nd_M',
            'final' => 'PDF/2023_ProtestInfo.pdf',
        ],
    };

    foreach ([
        1 => ['15W12', '男子15G・女子12G'],
        2 => ['30W24', '男子30G・女子24G'],
        3 => ['45W36', '男子45G・女子36G'],
        4 => ['60W48', '男子60G・女子48G'],
    ] as $day => [$suffix, $games]) {
        $documents[] = $document($year, 'first', "第1次 東日本 {$day}日目（{$games}）", $paths['first_east'].$suffix.'.pdf');
        $documents[] = $document($year, 'first', "第1次 西日本 {$day}日目（{$games}）", $paths['first_west'].$suffix.'.pdf');
        $documents[] = $document($year, 'second', "第2次 {$day}日目（{$games}）", $paths['second'].$suffix.'.pdf');
    }

    $documents[] = $document($year, 'final', '最終結果・合格者', $paths['final']);

    $years[$year] = [
        'year' => $year,
        'male_generation' => "第{$maleGeneration}期",
        'female_generation' => "第{$femaleGeneration}期",
        'held' => true,
        'note' => null,
        'documents' => $documents,
    ];
}

$years[2024] = [
    'year' => 2024,
    'male_generation' => '第62期',
    'female_generation' => '第56期',
    'held' => true,
    'note' => null,
    'documents' => [
        $document(2024, 'first', '第1次 1日目（男子15G・女子12G）', 'Result/1st/!st_M15W12.pdf'),
        $document(2024, 'first', '第1次 2日目（男子30G・女子24G）', 'Result/1st/1st_M30W24.pdf'),
        $document(2024, 'first', '第1次 3日目（男子45G・女子36G）', 'Result/1st/1st_M45W36.pdf'),
        $document(2024, 'first', '第1次 4日目（男子60G・女子48G）', 'Result/1st/1st_M60W48.pdf'),
        $document(2024, 'second', '第2次 1日目（男子15G・女子12G）', 'Result/2nd/2nd_M15W12.pdf'),
        $document(2024, 'second', '第2次 2日目（男子30G・女子24G）', 'Result/2nd/2nd_M30W24.pdf'),
        $document(2024, 'second', '第2次 3日目（男子45G・女子36G）', 'Result/2nd/2nd_M45W36.pdf'),
        $document(2024, 'second', '第2次 4日目（男子60G・女子48G）', 'Result/2nd/2nd_M60W48.pdf'),
        $document(2024, 'final', '最終結果・合格者', 'PDF/M62W56.pdf'),
    ],
];

foreach ([2025, 2026] as $year) {
    $maleGeneration = $year - 1962;
    $femaleGeneration = $year - 1968;
    $documents = [];

    foreach ([
        1 => ['M15G', 'W12G', '男子15G', '女子12G'],
        2 => ['M30G', 'W24G', '男子30G', '女子24G'],
        3 => ['M45G', 'W36G', '男子45G', '女子36G'],
        4 => ['M60G', 'W48G', '男子60G', '女子48G'],
    ] as $day => [$maleFile, $femaleFile, $maleGames, $femaleGames]) {
        $documents[] = $document($year, 'first', "第1次 {$day}日目 {$maleGames}成績", "1st/1st_{$maleFile}.pdf");
        $documents[] = $document($year, 'first', "第1次 {$day}日目 {$femaleGames}成績", "1st/1st_{$femaleFile}.pdf");

        $maleSecondFile = $year === 2026
            ? '2nd/M_2nd_'.substr($maleFile, 1).'.pdf'
            : '2nd/'.str_replace('G', '', $maleFile).'.pdf';
        $femaleSecondFile = $year === 2026
            ? '2nd/W_2nd_'.substr($femaleFile, 1).'.pdf'
            : '2nd/'.str_replace('G', '', $femaleFile).'.pdf';
        $documents[] = $document($year, 'second', "第2次 {$day}日目 {$maleGames}成績", $maleSecondFile);
        $documents[] = $document($year, 'second', "第2次 {$day}日目 {$femaleGames}成績", $femaleSecondFile);
    }

    $documents[] = $document($year, 'final', '最終結果・合格者', "{$year}_Newcomer.pdf");
    if ($year === 2026) {
        $documents[] = $document($year, 'final', '追加合格者', '2026_Newcomer_02.pdf');
    }

    $years[$year] = [
        'year' => $year,
        'male_generation' => "第{$maleGeneration}期",
        'female_generation' => "第{$femaleGeneration}期",
        'held' => true,
        'note' => null,
        'documents' => $documents,
    ];
}

krsort($years);

return [
    'source_checked_at' => '2026-09-03',
    'years' => $years,
];
