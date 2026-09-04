<?php

use App\Models\InformationFile;

test('information file paths cannot escape public storage roots', function () {
    $file = new InformationFile(['file_path' => '../../.env']);

    expect($file->normalizedPath())->toBe('')
        ->and($file->absolutePath())->toBeNull()
        ->and($file->publicUrl())->toBeNull();
});
