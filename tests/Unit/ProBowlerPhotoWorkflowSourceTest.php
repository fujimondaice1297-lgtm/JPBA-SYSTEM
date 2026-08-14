<?php

use App\Models\ProBowler;
use App\Services\ProBowlerPhotoService;
use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

test('official profile photo URLs are resolved safely', function () {
    $service = app(ProBowlerPhotoService::class);
    $bowler = new ProBowler([
        'license_no' => 'F00000598',
        'public_image_path' => '/.file/prof/W598_近藤菜帆25.jpg',
    ]);

    expect($service->officialSourceUrl($bowler))
        ->toBe('https://www.jpba1.jp/.file/prof/W598_%E8%BF%91%E8%97%A4%E8%8F%9C%E5%B8%8625.jpg');

    $html = '<div class="player-detail"><img src="/assets/img/prof/male/1219.jpg"></div>';
    expect($service->extractOfficialPhotoUrl($html))
        ->toBe('https://www.jpba1.jp/assets/img/prof/male/1219.jpg');

    expect($service->relativeStoragePath('/storage/profiles/m00001219/2026/official-a.jpg'))
        ->toBe('profiles/m00001219/2026/official-a.jpg');
    expect($service->relativeStoragePath('profiles/m00001219/2026/official-a.jpg'))
        ->toBe('profiles/m00001219/2026/official-a.jpg');
});

test('photo migration replacement and delivery routes are connected', function () {
    expect(Route::has('players.photo'))->toBeTrue();
    expect(Route::has('athlete.photo.update'))->toBeTrue();

    $command = file_get_contents(app_path('Console/Commands/ImportOfficialPlayerPhotosCommand.php'));
    $service = file_get_contents(app_path('Services/ProBowlerPhotoService.php'));
    $controller = file_get_contents(app_path('Http/Controllers/ProBowlerController.php'));
    $form = file_get_contents(resource_path('views/pro_bowlers/athlete_form.blade.php'));

    expect($command)
        ->toContain('jpba:import-official-player-photos')
        ->toContain('{--force')
        ->toContain('already_local')
        ->toContain('error_rows');
    expect($service)
        ->toContain("'profiles/%s/%d/%s-%s.%s'")
        ->toContain('getimagesizefromstring')
        ->toContain("['jpba1.jp', 'www.jpba1.jp']")
        ->toContain("route('players.photo'");
    expect($controller)->toContain('function updateProfilePhoto');
    expect($form)
        ->toContain('公開プロフィール写真')
        ->toContain('写真を更新')
        ->toContain("route('athlete.photo.update'");
});

test('the four requested screens use the shared player photo', function () {
    $publicProfile = file_get_contents(app_path('Http/Controllers/PublicProfileController.php'));
    $entryBalls = file_get_contents(app_path('Http/Controllers/TournamentEntryBallController.php'));
    $scoreView = file_get_contents(resource_path('views/scores/result.blade.php'));
    $publicTournament = file_get_contents(resource_path('views/public/tournaments/show.blade.php'));

    expect($publicProfile)->toContain('$p->public_photo_url');
    expect($entryBalls)->toContain('$entry->bowler?->public_photo_url');
    expect($scoreView)->toContain("'portrait_url' => \$bowler->public_photo_url");
    expect($publicTournament)
        ->toContain('$row->pro_photo_url')
        ->toContain("route('public.players.show'");
});
