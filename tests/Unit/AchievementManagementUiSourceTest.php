<?php

it('provides edit and delete controls for achievements on the player editor', function () {
    $root = dirname(__DIR__, 2);
    $summary = file_get_contents(
        $root . '/resources/views/pro_bowlers/partials/awards_summary.blade.php'
    );
    $form = file_get_contents(
        $root . '/resources/views/pro_bowlers/athlete_form.blade.php'
    );

    expect($summary)
        ->toContain("route('record_types.edit'")
        ->toContain('achievement-del-')
        ->toContain('>編集</a>')
        ->toContain('>削除</button>')
        ->and($form)
        ->toContain("route('admin.record_types.destroy'")
        ->toContain('name="return_to" value="pro_bowler_edit"');
});

it('returns record updates and deletes to the player editor when requested', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents(
        $root . '/app/Http/Controllers/RecordTypeController.php'
    );
    $recordForm = file_get_contents(
        $root . '/resources/views/record_types/_form.blade.php'
    );

    expect($controller)
        ->toContain('redirectAfterMutation')
        ->toContain("\$request->input('return_to') === 'pro_bowler_edit'")
        ->toContain("route('pro_bowlers.edit', \$bowlerId)")
        ->and($recordForm)
        ->toContain('name="return_to" value="pro_bowler_edit"');
});
