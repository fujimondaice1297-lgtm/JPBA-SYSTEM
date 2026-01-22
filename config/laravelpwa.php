<?php

return [

    /*
    |--------------------------------------------------------------------------
    | アプリ識別名（iOSのホーム画面名にも使われる）
    |--------------------------------------------------------------------------
    */
    'name' => 'JPBA',

    /*
    |--------------------------------------------------------------------------
    | マニフェスト設定
    |--------------------------------------------------------------------------
    */
    'manifest' => [
        'name' => 'JPBA',
        'short_name' => 'JPBA',
        // アプリ起動直後に開くURL（ログイン後の会員トップにしたければ '/member' でもOK）
        'start_url' => '/member',
        // アイコン背景やスプラッシュの背景色
        'background_color' => '#ffffff',
        // Safari等のUIカラー（JPBAカラーに寄せるなら変更OK）
        'theme_color' => '#ff0000ff',
        'display' => 'standalone',
        'orientation'=> 'portrait',
        'status_bar'=> 'black-translucent',

        // アイコン一式（下のパスにPNGを置く／サイズはできるだけ実サイズで用意）
        'icons' => [
            '72x72'   => 'images/icons/icon-72x72.png',
            '96x96'   => 'images/icons/icon-96x96.png',
            '128x128' => 'images/icons/icon-128x128.png',
            '144x144' => 'images/icons/icon-144x144.png',
            '152x152' => 'images/icons/icon-152x152.png',
            '180x180' => 'images/icons/icon-180x180.png', // iOSのapple-touch-icon推奨サイズ
            '192x192' => 'images/icons/icon-192x192.png',
            '384x384' => 'images/icons/icon-384x384.png',
            '512x512' => 'images/icons/icon-512x512.png',
        ],

        // 追加したければ任意（説明など）
        'description' => 'JPBA 会員向けアプリ',
        'lang' => 'ja',
        'dir'  => 'ltr',
        'display_override' => ['standalone','fullscreen'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Worker 周り（既定のままでOK）
    |--------------------------------------------------------------------------
    */
    'service_worker' => [
        'file' => 'pwa-service-worker.js', // public/ に生成されるファイル名（パッケージが配布）
        'cache' => [
            'enabled' => true,
            'name' => 'jpba-pwa-cache',
            'max_entries' => 200,
            'strategy' => 'NetworkFirst',
        ],
    ],
];
