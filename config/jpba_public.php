<?php

return [
    'primary_nav' => [
        ['label' => 'JPBAについて', 'route' => 'public.about'],
        ['label' => 'スケジュール', 'route' => 'public.schedule'],
        ['label' => '選手データ', 'route' => 'public.players.index'],
        ['label' => 'トーナメント', 'route' => 'public.tournaments.index'],
        ['label' => 'インストラクター', 'route' => 'public.instructors.index'],
        ['label' => 'プロテスト', 'url' => 'https://www.jpba1.jp/protest/index.html'],
        ['label' => 'トピックス', 'url' => 'https://www.jpba.or.jp/topics.html'],
    ],

    'utility_links' => [
        ['label' => '更新履歴', 'url' => 'https://www.jpba.or.jp/topics.html'],
        ['label' => 'プロボウラー専用ページ', 'route' => 'login'],
    ],

    'featured_pdf_links' => [
        [
            'label' => 'JPBAツアー ご観戦時のご案内',
            'url' => 'https://www.jpba.or.jp/information/tournament/PDF/JPBA_TournamentSpectatorRules.pdf',
        ],
        [
            'label' => 'ウレタンボールの使用規制について',
            'url' => 'https://www.jpba1.jp/mypage/notification/document/2026/RegulationsAboutUrethaneBalls_260407.pdf',
        ],
    ],

    'channel_links' => [],

    'association' => [
        'overview' => [
            ['label' => '名称', 'value' => '公益社団法人日本プロボウリング協会 / Japan Professional Bowling Association / JPBA'],
            ['label' => '設立', 'value' => '1967年1月27日 発足 / 1991年7月10日 社団法人化 / 2013年7月1日 公益社団法人に移行'],
            ['label' => '所在地', 'value' => '〒105-0023 東京都港区芝浦1-13-10 第三東運ビル2F'],
            ['label' => '連絡', 'value' => 'TEL: 03-6436-0310（代表） / FAX: 03-3454-6140'],
            ['label' => '会員数', 'value' => '男子736名 / 女子363名 / プロ・インストラクター8名 / 計1,107名（2026年3月11日現在）'],
        ],
        'description' => [
            '本協会は、我が国におけるプロボウリングを統括し代表する団体として、健全なるプロフェッショナルボウラーの育成に努めるとともに、指導者の資格認定・登録、養成・研修を行います。',
            '児童・青少年及び一般愛好者への普及・育成を図り、ボウリング競技会を通じて普及・振興と国際親善に寄与することを目的とします。',
        ],
        'businesses' => [
            'プロボウラーの資格認定及び登録',
            'プロボウラーの指導者及び一般の指導者の資格認定・登録並びに養成・研修',
            'ボウリングの技術及びマナーに関する調査研究、指導及び奨励',
            'ボウリングを通じてのスポーツ医・科学の調査研究',
            'ボウリング競技会の開催と公認',
            'ボウリング関係諸団体等が主催する競技会又は講習会の指導及び援助',
            'ボウリングに関する刊行物の発行',
            'その他本協会の目的を達成するために必要な事業',
        ],
        'documents' => [
            ['label' => '定款(PDF)', 'url' => 'https://www.jpba1.jp/assets/pdf/Association/Articles_202007.pdf'],
            ['label' => '会長挨拶', 'url' => 'https://www.jpba1.jp/association/president.html'],
            ['label' => '運営機構図', 'url' => 'https://www.jpba1.jp/association/map.html'],
            ['label' => '役員・代議員名簿(PDF)', 'url' => 'https://www.jpba1.jp/assets/pdf/Association/2025/2025_2026_Directors.pdf'],
            ['label' => '2026年度事業計画(PDF)', 'url' => 'https://www.jpba1.jp/assets/pdf/Association/2026/Plan_2026.pdf'],
            ['label' => '2026年度収支予算(PDF)', 'url' => 'https://www.jpba1.jp/assets/pdf/Association/2026/Budget_2026.pdf'],
            ['label' => '2025年度事業報告(PDF)', 'url' => 'https://www.jpba1.jp/assets/pdf/Association/2026/Report_2025.pdf'],
            ['label' => '2025年度正味財産増減計算書(PDF)', 'url' => 'https://www.jpba1.jp/assets/pdf/Association/2026/Settlement_2025.pdf'],
        ],
    ],

    'instructor' => [
        'summary' => [
            '公益社団法人日本プロボウリング協会では、ボウリングの普及・指導に携わるインストラクター制度を設けています。',
            '講習情報、スクール情報、テキスト販売、制度概要、ライセンス別情報を公開導線として整理し、名簿は instructor_registry を正本として表示します。',
        ],
        'feature_links' => [
            [
                'label' => 'インストラクター講習情報',
                'description' => '講習会・研修会など、インストラクター向けのお知らせを確認できます。',
                'url' => 'https://www.jpba1.jp/instructor/instructor_guide.html',
            ],
            [
                'label' => 'ボウリングスクール開講のご案内',
                'description' => 'スクール開講に関する案内、要件、関連資料を確認できます。',
                'url' => 'https://www.jpba1.jp/instructor/school_guide.html',
            ],
            [
                'label' => 'インストラクターテキスト販売',
                'description' => '指導者向けテキストや教材の販売案内です。',
                'url' => 'https://www.jpba1.jp/instructor/textbook.html',
            ],
            [
                'label' => 'インストラクター制度概要',
                'description' => 'JPBAインストラクター制度の概要を確認できます。',
                'url' => 'https://www.jpba1.jp/instructor/overview.html',
            ],
        ],
        'license_links' => [
            ['label' => 'A級インストラクター', 'url' => 'https://www.jpba1.jp/instructor/ins_a.html'],
            ['label' => 'B級インストラクター', 'url' => 'https://www.jpba1.jp/instructor/ins_b.html'],
            ['label' => 'C級インストラクター', 'url' => 'https://www.jpba1.jp/instructor/ins_c.html'],
            ['label' => 'プロ・インストラクター', 'url' => 'https://www.jpba1.jp/instructor/ins_pro.html'],
            ['label' => '1級・2級インストラクター', 'url' => 'https://www.jpba1.jp/instructor/ins_12.html'],
            ['label' => '健康ボウリング指導員', 'url' => 'https://www.jpba1.jp/instructor/ins_shidoin.html'],
        ],
    ],

    'footer_links' => [
        ['label' => 'お問い合わせ', 'url' => 'https://www.jpba.or.jp/contact/'],
        ['label' => '取材のお申込み', 'url' => 'https://www.jpba1.jp/media/index.html'],
        ['label' => '特定商取引法に基づく表記', 'url' => 'https://www.jpba1.jp/ovservance/index.html'],
        ['label' => 'プライバシーポリシー', 'url' => 'https://www.jpba1.jp/policy/index.html'],
    ],
];
