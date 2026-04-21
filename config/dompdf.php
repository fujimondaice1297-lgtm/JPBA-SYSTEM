<?php

return [

    'show_warnings' => false,
    'public_path' => public_path(),
    'convert_entities' => true,

    'options' => [
        'fontDir' => storage_path('fonts'),
        'fontCache' => storage_path('fonts'),
        'tempDir' => sys_get_temp_dir(),
        'chroot' => realpath(base_path()) ?: base_path(),
        'logOutputFile' => storage_path('logs/dompdf.html'),

        'defaultMediaType' => 'screen',
        'defaultPaperSize' => 'a4',
        'defaultPaperOrientation' => 'portrait',
        'defaultFont' => 'ipaexg',

        'dpi' => 96,
        'fontHeightRatio' => 1.1,

        'isPhpEnabled' => false,
        'isRemoteEnabled' => false,
        'isJavascriptEnabled' => true,
        'isHtml5ParserEnabled' => true,

        'allowedRemoteHosts' => null,
        'isFontSubsettingEnabled' => false,
        'isPdfAEnabled' => false,

        'pdfBackend' => 'CPDF',
        'pdflibLicense' => '',
        'adminUsername' => 'user',
        'adminPassword' => 'password',
        'artifactPathValidation' => null,
    ],
];