<?php

return [

    'font_dir' => storage_path('fonts/'),
    'font_cache' => storage_path('fonts/'),

    'default_font' => 'ipaexg',

    'font_data' => [
        'ipaexg' => [
            'R' => 'ipaexg.ttf',
            'useOTL' => 0xFF, // ←0x00から0xFFに変えてみる（日本語文字幅とか対応）
            'useKashida' => 75,
        ],
    ],

    // 他の設定（もしあるならそのままで）
];
