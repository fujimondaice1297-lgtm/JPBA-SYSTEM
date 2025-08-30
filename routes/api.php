<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProBowlerController;
use App\Http\Controllers\Api\ApprovedBallController;
use App\Http\Controllers\TournamentEntryBallController;
use App\Http\Controllers\DrawController;

Route::get('/approved-balls/filter', [ApprovedBallController::class, 'filter']);

Route::get('/pro_bowlers', [ProBowlerController::class, 'search']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/entries/{entry}/shift-draw', [DrawController::class, 'shiftApi']);
    Route::post('/entries/{entry}/lane-draw', [DrawController::class, 'laneApi']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    // ▼ ボール紐付け（API：トークン認証）
    Route::post('/tournament_entries/{entry}/balls', [TournamentEntryBallController::class, 'store'])
        ->name('api.tournament_entries.balls.store');

    Route::delete('/tournament_entries/{entry}/balls/{ball}', [TournamentEntryBallController::class, 'destroy'])
        ->name('api.tournament_entries.balls.destroy');
});
