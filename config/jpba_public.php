<?php

return [
    'primary_nav' => [
        ['label' => 'JPBAについて', 'route' => 'public.about'],
        ['label' => 'スケジュール', 'route' => 'public.schedule'],
        ['label' => '選手データ', 'route' => 'public.players.index'],
        ['label' => 'トーナメント', 'route' => 'public.tournaments.index'],
        ['label' => 'インストラクター', 'route' => 'public.instructors.index'],
        ['label' => 'プロテスト', 'route' => 'public.protest'],
        ['label' => 'トピックス', 'route' => 'public.topics'],
    ],

    'utility_links' => [
        ['label' => '更新履歴', 'route' => 'public.topics'],
        ['label' => 'プロボウラー専用ページ', 'route' => 'login'],
    ],

    'featured_pdf_links' => [
        [
            'label' => '2026 JPBAトーナメント予定表',
            'url' => 'https://www.jpba.or.jp/information/tournament/tournament2026/PDF/2026_TournamentSchedule_260622.pdf',
        ],
        [
            'label' => 'JPBAツアー ご観戦時のご案内',
            'url' => 'https://www.jpba.or.jp/information/tournament/PDF/JPBA_TournamentSpectatorRules.pdf',
        ],
        [
            'label' => 'ウレタンボールの使用規制について',
            'url' => 'https://www.jpba1.jp/mypage/notification/document/2026/RegulationsAboutUrethaneBalls_260407.pdf',
        ],
    ],

    'channel_links' => [
        [
            'label' => 'JPBA LIVEチャンネル',
            'url' => 'https://www.youtube.com/channel/UCZhBTBHPsEFEO047w0ynZ0Q',
        ],
        [
            'label' => 'io.LEAGUEチャンネル',
            'url' => 'https://www.youtube.com/@io.LEAGUE',
        ],
        [
            'label' => 'io.LEAGUE Official Website',
            'url' => 'https://ioleague.jp/',
        ],
    ],

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
            '講習情報、スクール情報、テキスト販売、制度概要、ライセンス別情報と、現在登録されているインストラクター名簿を確認できます。',
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

    'protest' => [
        'intro' => [
            'プロボウラー資格取得テスト、通称「プロテスト」は、プロボウラー資格を取得するための実技、面接、身体検査、筆記、入会時研修を含む選考です。',
            '現行サイトの説明を維持しつつ、今後は受験者講習会、申請、実技スコア、合否、公開結果PDFをDB正本から表示する前提で整理します。',
        ],
        'flow' => [
            [
                'title' => '1. 受験者講習会を受講する',
                'body' => '受験予定前年の9月初旬から10月下旬に全国地区で開催される受験者講習会を受講します。認定1級・2級インストラクターのライセンス保持者は免除対象です。',
            ],
            [
                'title' => '2. 受験申請をする',
                'body' => '毎年2月頃に実施要項と申請期間が告知されます。申請フォーム、誓約書、住民票、推薦状、証明写真、受験料などの提出・振込期限を管理します。',
            ],
            [
                'title' => '3. 第1次テストを受験する',
                'body' => '男子は4日間60ゲームで200アベレージ以上、女子は4日間48ゲームで190アベレージ以上が基準です。2日目時点の足切り条件も扱います。',
            ],
            [
                'title' => '4. 第2次テストを受験する',
                'body' => '1次合格者および1次免除者が実技テストを行います。男子60ゲーム200アベレージ、女子48ゲーム190アベレージを合格基準として扱います。',
            ],
            [
                'title' => '5. 第3次テストを受験する',
                'body' => '面接テスト、身体検査、筆記テスト、入会時研修を経て、すべて合格した受験者がプロボウラー資格を取得します。',
            ],
        ],
        'links' => [
            ['label' => '現行プロテストページ', 'url' => 'https://www.jpba1.jp/protest/index.html'],
            ['label' => '実施概要', 'url' => 'https://www.jpba1.jp/protest/guide.html'],
            ['label' => '認定1級・2級インストラクター', 'url' => 'https://www.jpba1.jp/instructor/ins_12.html'],
        ],
    ],

    'topics' => [
        'lead' => '大会終了記事、達成記録、社会貢献活動、プロボウラー紹介、大会ページリンクを公開記事として扱います。新サイト側では informations を正本にし、画像やPDFは添付ファイルとして紐づけます。',
        'legacy_links' => [
            ['label' => '現行トピックス', 'url' => 'https://www.jpba.or.jp/topics.html'],
            ['label' => '社会貢献活動', 'url' => 'https://www.jpba.or.jp/information/Charity/Charity.html'],
            ['label' => 'プロボウラー紹介', 'url' => 'https://www.jpba.or.jp/interview/index.html'],
            ['label' => '大会ページ一覧', 'url' => 'https://www.jpba.or.jp/information/tournament/tournament.html'],
        ],
    ],

    'static_pages' => [
        'contact' => [
            'title' => 'お問い合わせ',
            'breadcrumb' => 'お問い合わせ',
            'summary' => [
                '公益社団法人日本プロボウリング協会へのお問い合わせ・ご要望は、現行サイトの問い合わせフォームで受け付けています。',
                '新システム側では、将来的に問い合わせ種別、対応状況、添付、返信履歴を管理できるようにする前提で公開導線を整理します。',
            ],
            'sections' => [
                [
                    'heading' => '受付内容',
                    'items' => ['氏名', '会社名', '電話番号', 'メールアドレス', 'ご用件', 'お問い合わせ本文'],
                ],
                [
                    'heading' => '協会連絡先',
                    'items' => ['TEL: 03-6436-0310', 'FAX: 03-3454-6140', '受付時間: 平日10時から17時'],
                ],
            ],
            'links' => [
                ['label' => '現行お問い合わせフォーム', 'url' => 'https://www.jpba1.jp/inquiry/index.html'],
            ],
        ],
        'media' => [
            'title' => '取材のお申込み',
            'breadcrumb' => '取材のお申込み',
            'summary' => [
                '大会取材では、感染予防対策およびセキュリティ強化のため、会場への出入りを管理する運用が案内されています。',
                '取材希望者は取材時遵守事項を確認し、原則として開催14日前までに申請します。',
            ],
            'sections' => [
                [
                    'heading' => '申請から受付まで',
                    'items' => ['取材時遵守事項を確認する', '取材申請書または申請フォームから申し込む', '事務局で内容確認後、会場受付の指示に従う'],
                ],
            ],
            'links' => [
                ['label' => '取材時遵守事項 PDF', 'url' => 'https://www.jpba1.jp/media/PDF/ComplianceRules_forMedia_230508.pdf'],
                ['label' => '取材申請書 PDF', 'url' => 'https://www.jpba1.jp/media/PDF/ApplicationSheet_forMedia_2024.pdf'],
                ['label' => '取材申請書フォーム', 'url' => 'https://ws.formzu.net/fgen/S86209866/'],
                ['label' => '現行ページ', 'url' => 'https://www.jpba1.jp/media/index.html'],
            ],
        ],
        'commerce' => [
            'title' => '特定商取引法に基づく表記',
            'breadcrumb' => '特定商取引法に基づく表記',
            'summary' => [
                '現行サイトの特定商取引法に基づく表記を、新サイトのフッター導線として整理します。',
            ],
            'table' => [
                ['label' => '事業者名', 'value' => '公益社団法人 日本プロボウリング協会（JPBA）'],
                ['label' => '所在地', 'value' => '〒105-0023 東京都港区芝浦1-13-10 第三東運ビル2F'],
                ['label' => '販売責任者', 'value' => '谷口 健'],
                ['label' => '販売価格', 'value' => '都度、該当箇所に表記します。'],
                ['label' => 'その他の料金', 'value' => '送料、現金書留郵便料および手数料'],
                ['label' => '代金の支払時期', 'value' => '現金書留（前払い）'],
                ['label' => '代金の支払方法', 'value' => '現金（現金書留にて郵送）'],
                ['label' => '商品の引渡時期', 'value' => '現金は即時、現金書留は到着後の翌日以降。在庫がない場合は都度相談。'],
                ['label' => '問合せ時間', 'value' => '平日午前10時から午後5時'],
            ],
            'sections' => [
                [
                    'heading' => '返品について',
                    'items' => [
                        '注文内容と異なる商品、破損、汚損があった場合は協会責任として個別対応します。',
                        '到着後7日以内に連絡が必要です。',
                        'お客様都合によるキャンセルや返品送料、返金手数料は原則としてお客様負担です。',
                    ],
                ],
            ],
            'links' => [
                ['label' => '現行ページ', 'url' => 'https://www.jpba1.jp/ovservance/index.html'],
            ],
        ],
        'privacy' => [
            'title' => 'プライバシーポリシー',
            'breadcrumb' => 'プライバシーポリシー',
            'summary' => [
                '公益社団法人日本プロボウリング協会は、個人情報の適切な保護と取扱いが重要であると認識し、個人情報保護に関するプライバシーポリシーを定めています。',
            ],
            'sections' => [
                ['heading' => '1. 法令等の遵守', 'items' => ['個人情報保護法、関係諸法令、各省庁ガイドライン、東京都が定める条例その他の規範を遵守します。']],
                ['heading' => '2. 個人情報の取得', 'items' => ['利用目的を明確にし、必要な範囲で適正かつ適法な手段により個人情報を取得します。']],
                ['heading' => '3. 個人情報の安全管理措置', 'items' => ['漏えい、滅失、き損などを防止するため、適切な安全管理措置を講じます。']],
                ['heading' => '4. 個人情報の第三者への提供', 'items' => ['法令等に基づく場合を除き、本人の同意なく保有個人情報を第三者に提供しません。業務委託に必要な範囲で提供する場合も十分に保護します。']],
                ['heading' => '5. 個人情報の利用目的', 'items' => ['明示または公表した目的以外に無断で利用しません。']],
                ['heading' => '6. 開示請求等の手続き', 'items' => ['利用目的の通知、開示、訂正、追加、削除、利用停止、消去、第三者提供停止などの請求に適切かつ迅速に対応します。']],
                ['heading' => '7. 問い合わせへの対応', 'items' => ['電話番号 03-6436-0310', '受付時間 平日10時から17時']],
                ['heading' => '8. 継続的改善', 'items' => ['情報技術の発展や社会的要請の変化を踏まえ、管理体制および取組みを継続的に見直します。']],
            ],
            'links' => [
                ['label' => '現行ページ', 'url' => 'https://www.jpba1.jp/policy/index.html'],
            ],
        ],
    ],

    'footer_links' => [
        ['label' => 'お問い合わせ', 'route' => 'public.contact'],
        ['label' => '取材のお申込み', 'route' => 'public.media'],
        ['label' => '特定商取引法に基づく表記', 'route' => 'public.commerce'],
        ['label' => 'プライバシーポリシー', 'route' => 'public.privacy'],
    ],
];
