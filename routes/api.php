<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VenueController;

use App\Http\Controllers\Api\ApprovedBallController as ApiApprovedBallController;
use App\Http\Controllers\Api\ProBowlerController as ApiProBowlerController;

// ▼ 追加（モバイル用APIコントローラ）
use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\MobileController as ApiMobileController;

// ▼ 既存（API側で使っている Web 名前空間のコントローラ）
use App\Http\Controllers\TournamentEntryBallController;
use App\Http\Controllers\DrawController;

/*
|--------------------------------------------------------------------------
| Public (no auth)
|--------------------------------------------------------------------------
*/
Route::post('auth/login', [ApiAuthController::class, 'login']); // {email,password} -> {token, user, bowler}

Route::get('approved-balls/filter', [ApiApprovedBallController::class, 'filter']);
Route::get('pro_bowlers', [ApiProBowlerController::class, 'search']);

Route::get('/venues', [VenueController::class, 'search'])->name('api.venues.search');
Route::get('/venues/{id}', [VenueController::class, 'show'])->name('api.venues.show');

// 組織マスタ（主催・協賛 等）検索API
Route::get('/organizations/search', [\App\Http\Controllers\OrganizationMasterController::class,'search'])->name('api.organizations.search');
Route::get('/organizations/{id}',   [\App\Http\Controllers\OrganizationMasterController::class,'show'])->name('api.organizations.show');

/*
|--------------------------------------------------------------------------
| Protected (Sanctum token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // --- 既存：抽選API ---
    Route::post('entries/{entry}/shift-draw', [DrawController::class, 'shiftApi']);
    Route::post('entries/{entry}/lane-draw',  [DrawController::class, 'laneApi']);

    // --- 既存：エントリーとボール紐付け（既存パスを維持） ---
    Route::post('tournament_entries/{entry}/balls',        [TournamentEntryBallController::class, 'store'])
        ->name('api.tournament_entries.balls.store');
    Route::delete('tournament_entries/{entry}/balls/{ball}', [TournamentEntryBallController::class, 'destroy'])
        ->name('api.tournament_entries.balls.destroy');

    // --- 追加：モバイル用の軽量API ---
    // 自分情報
    Route::get('me', [ApiMobileController::class, 'me']);

    // 大会
    Route::get('tournaments', [ApiMobileController::class, 'tournaments']);     // 一覧
    Route::post('entries',    [ApiMobileController::class, 'createEntry']);     // 参加（重複時は既存返す）
    Route::get('entries',     [ApiMobileController::class, 'myEntries']);       // 自分のエントリー一覧

    // エントリー単位の使用ボール（モバイル用の別名ルート）
    Route::get('entries/{entry}/balls', [ApiMobileController::class, 'entryBalls']);
    Route::post('entries/{entry}/balls', [ApiMobileController::class, 'saveEntryBalls']);

    // マイボール（個人）
    Route::get('used-balls',  [ApiMobileController::class, 'usedBalls']);
    Route::post('used-balls', [ApiMobileController::class, 'createUsedBall']);

    // 成績（閲覧）
    Route::get('results', [ApiMobileController::class, 'results']);
});
