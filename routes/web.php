<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\ProBowler;
use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ChangePasswordController;

use App\Http\Controllers\{
    ProBowlerController, TournamentController, TournamentResultController, RecordTypeController,
    InstructorController, PrizeDistributionController, PointDistributionController,
    ApprovedBallController, ApprovedBallImportController, UsedBallController, ProfileController,
    TournamentProController, TpRegistrationController, RankingController, PerfectRecordController,
    ProGroupController, CertificateController, RegisteredBallController, ComplianceController,
    ProBowlerTrainingController, AuthController, BulkTrainingController, TrainingReportController,
    CalendarController, CalendarEventController, ProBowlerTitleController, TitleSyncController,
    MemberDashboardController,InformationController,TournamentEntryBallController,TournamentEntryController,
    DrawController,
};

/* ========================
   トップ
======================== */
Route::get('/', fn () => view('welcome'));

/* ========================
   認証（重複排除）
======================== */

/* ====== Auth (login / logout) ====== */
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/* ====== Register ====== */
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store']); // ← store に統一

/* ====== Password (forgot / reset) ====== */
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');

/* ====== ログイン後（会員マイページ） ====== */
Route::middleware(['auth'])->group(function () {
    Route::get('/member', [MemberDashboardController::class,'index'])->name('member.dashboard');
    Route::get('/password/change', [ChangePasswordController::class, 'showForm'])->name('password.change.form');
    Route::post('/password/change', [ChangePasswordController::class, 'update'])->name('password.update.self');

    // --- ボール紐付け（WEB：セッション認証で使う） ---
    // テストフォーム表示（← ここを Route::view から置換）
    Route::get('/test/attach-ball', function () {
        return view('test_attach_ball', ['entryId' => 0]); // 0 をプレースホルダとして渡す
    })->name('test.attach-ball');

    // 大会エントリーフォーム
    Route::get('/entry/select', [TournamentEntryController::class, 'select'])->name('tournament.entry.select');
    Route::post('/entry/select', [TournamentEntryController::class, 'storeSelection'])->name('tournament.entry.select.store');

    // ▼ ボール紐付け（WEB：セッション認証）
    Route::post(
        '/tournament_entries/{entry}/balls',
        [\App\Http\Controllers\TournamentEntryBallController::class, 'store']
    )->name('web.tournament_entries.balls.store');

    Route::delete(
        '/tournament_entries/{entry}/balls/{ball}',
        [\App\Http\Controllers\TournamentEntryBallController::class, 'destroy']
    )->name('web.tournament_entries.balls.destroy');

    // 会員：エントリー済み大会にボールを登録（複数・最大12）
    Route::get(
        '/member/entries/{entry}/balls',
        [\App\Http\Controllers\TournamentEntryBallController::class, 'edit']
    )->name('member.entries.balls.edit');

    Route::post(
        '/member/entries/{entry}/balls',
        [\App\Http\Controllers\TournamentEntryBallController::class, 'bulkStore']
    )->name('member.entries.balls.store');

    // --- 会員マイページ：自分の選手ページ編集 ---
    Route::get('/athlete', [ProBowlerController::class, 'editSelf'])->name('athlete.edit');
    Route::put('/athlete/{bowler}', [ProBowlerController::class, 'updateSelf'])->name('athlete.update');
});

Route::middleware(['auth'])->group(function () {
    // シフト抽選（会員）
    Route::post('/member/entries/{entry}/shift-draw', [DrawController::class, 'shift'])
        ->name('member.entries.shift.draw');

    // レーン抽選（会員）
    Route::post('/member/entries/{entry}/lane-draw', [DrawController::class, 'lane'])
        ->name('member.entries.lane.draw');

    // 大会側の設定（管理者のみ想定）
    Route::get('/admin/tournaments/{tournament}/draw-settings', [DrawController::class, 'settings'])
        ->name('admin.tournaments.draw.settings');
    Route::post('/admin/tournaments/{tournament}/draw-settings', [DrawController::class, 'saveSettings'])
        ->name('admin.tournaments.draw.settings.save');
});


/* ====== 管理画面 ====== */
Route::middleware(['auth','can:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('/', [AdminHomeController::class, 'index'])->name('home');
        // 他の管理画面ルート…
    });


/* ========================
   ProBowlers
======================== */
Route::get('/athletes', [ProBowlerController::class, 'index'])->name('athlete.index');
Route::get('/pro-bowlers/create', [ProBowlerController::class, 'create'])->name('pro_bowlers.create');
Route::post('/pro-bowlers', [ProBowlerController::class, 'store'])->name('pro_bowlers.store');
Route::get('/pro_bowlers', [ProBowlerController::class, 'index'])->name('pro_bowlers.index');
Route::get('/pro_bowlers/list', [ProBowlerController::class, 'list'])->name('pro_bowlers.list');
Route::get('/pro_bowlers/{id}/edit', [ProBowlerController::class, 'edit'])->name('pro_bowlers.edit');
Route::put('/pro_bowlers/{id}', [ProBowlerController::class, 'update'])->name('pro_bowlers.update');
Route::get('/pro_bowlers/form', fn () => view('pro_bowlers.athlete_form'))->name('athletes.create');
Route::post('/athletes/store', fn () => '登録完了（仮）')->name('athletes.store');

Route::middleware(['auth'])->group(function () {
    Route::get('/admin/trainings/bulk',  [BulkTrainingController::class, 'create'])->name('trainings.bulk');
    Route::post('/admin/trainings/bulk', [BulkTrainingController::class, 'store'])->name('trainings.bulk.store');

    Route::get('/admin/trainings/reports/{scope?}', [TrainingReportController::class, 'index'])
        ->whereIn('scope', ['compliant','missing','expired','expiring'])
        ->name('trainings.reports');
Route::get('/pro_bowlers/import', [\App\Http\Controllers\ProBowlerImportController::class, 'form'])->name('pro_bowlers.import_form');
Route::post('/pro_bowlers/import', [\App\Http\Controllers\ProBowlerImportController::class, 'import'])->name('pro_bowlers.import');
});

/* ========================
  Tournament（大会マスタ）
=========================*/
Route::resource('tournaments', TournamentController::class);

/* 大会成績トップ（＝大会検索ページ） */
Route::get('/tournament_results', [TournamentResultController::class, 'list'])
    ->name('tournament_results.index');

/* 大会別 成績（ネスト資源）+ shallow */
Route::resource('tournaments.results', TournamentResultController::class)
    ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
    ->shallow();

/* 成績：追加アクション */
Route::post('/tournaments/{tournament}/results/apply-awards-points',
    [TournamentResultController::class, 'applyAwardsAndPoints']
)->name('tournaments.results.apply_awards_points');

Route::post('/tournaments/{tournament}/results/sync-titles',
    [TournamentResultController::class, 'syncTitles']
)->name('tournaments.results.sync');

/* 年間ランキング・PDF */
Route::get('/tournament_results/rankings', [TournamentResultController::class, 'rankings'])
    ->name('tournament_results.rankings');
Route::get('/tournament_results/pdf', [TournamentResultController::class, 'exportPdf'])
    ->name('tournament_results.pdf');

/* 旧フォーム互換（単発登録 / 一括登録） */
Route::get('/tournament_results/create', [TournamentResultController::class, 'create'])
    ->name('tournament_results.create');
Route::post('/tournament_results', [TournamentResultController::class, 'store'])
    ->name('tournament_results.store');
Route::get('/tournament_results/{result}/edit', [TournamentResultController::class, 'edit'])
    ->name('tournament_results.edit');
Route::put('/tournament_results/{result}', [TournamentResultController::class, 'update'])
    ->name('tournament_results.update');
Route::delete('/tournament_results/{result}', [TournamentResultController::class, 'destroy'])
    ->name('tournament_results.destroy');

Route::get('/tournament_results/batch-create', [TournamentResultController::class, 'batchCreate'])
    ->name('tournament_results.batchCreate');
Route::post('/tournament_results/batch-store', [TournamentResultController::class, 'batchStore'])
    ->name('tournament_results.batchStore');

/* ========================
   賞金配分 / ポイント配分（ネスト資源）
=========================*/
Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
    Route::resource('prize_distributions', PrizeDistributionController::class);
    Route::resource('point_distributions', PointDistributionController::class);
});

/* ========================
   Instructor
======================== */
Route::resource('instructors', InstructorController::class);
Route::get('/instructors/export-pdf', [InstructorController::class, 'exportPdf'])
    ->name('instructors.exportPdf');
Route::get('/instructors/license/{license_no}/edit', [InstructorController::class, 'edit'])
    ->name('instructors.edit_by_license');
Route::get('/instructors/{license_no}/edit', function (string $license_no) {
    if (preg_match('/^[A-Za-z].*/', $license_no)) {
        return redirect()->route('instructors.edit_by_license', ['license_no' => $license_no], 301);
    }
    abort(404);
})->name('instructors.edit.legacy');
Route::get('/certified_instructors/{license_no}/edit', [InstructorController::class, 'edit'])
    ->name('certified_instructors.edit');

/* ========================
   Approved / Registered / Used Balls
======================== */
Route::resource('approved_balls', ApprovedBallController::class);
Route::post('/approved_balls/store-multiple', [ApprovedBallController::class, 'storeMultiple'])->name('approved_balls.store_multiple');
Route::get('/approved_balls/import', [ApprovedBallImportController::class, 'showImportForm'])->name('approved_balls.import_form');
Route::post('/approved_balls/import', [ApprovedBallImportController::class, 'import'])->name('approved_balls.import');
Route::resource('registered_balls', RegisteredBallController::class)->except(['show']);
Route::resource('used_balls', UsedBallController::class)->except(['show']);

/* ========================
   その他ページ
======================== */
Route::get('/tournament_pro', [TournamentProController::class, 'index'])->name('tournament_pro.index');
Route::get('/tp_registration', [TpRegistrationController::class, 'index'])->name('tp_registration.index');
Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
Route::get('/perfect_records', [PerfectRecordController::class, 'index'])->name('perfect_records.index');
Route::get('/pro_groups', [ProGroupController::class, 'index'])->name('pro_groups.index');
Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');

Route::middleware(['auth'])->group(function(){
    Route::get('/admin/compliance', [ComplianceController::class,'index'])->name('compliance.index');
    Route::post('/admin/compliance/notify', [ComplianceController::class,'notify'])->name('compliance.notify');
});
Route::middleware('auth')->group(function () {
    Route::post('/pro_bowlers/{pro_bowler}/trainings', [ProBowlerTrainingController::class, 'store'])->name('pro_bowler_trainings.store');
});

// 一般公開
Route::get('/info', [InformationController::class,'index'])
    ->name('informations.index');
// 会員向け
Route::middleware('auth')->get('/member/info', [InformationController::class,'member'])
    ->name('informations.member');

/* ========================
   カレンダー
======================== */
Route::get('/calendar/{year?}', [CalendarController::class,'annual'])->whereNumber('year')->name('calendar.annual');
Route::get('/calendar/{year}/{month}', [CalendarController::class,'monthly'])->whereNumber('year')->whereNumber('month')->name('calendar.monthly');
Route::get('/calendar/{year}/pdf', [CalendarController::class,'annualPdf'])->whereNumber('year')->name('calendar.annual.pdf');
Route::get('/calendar/{year}/{month}/pdf', [CalendarController::class,'monthlyPdf'])->whereNumber('year')->name('calendar.monthly.pdf');

Route::prefix('calendar-events')->name('calendar_events.')->group(function () {
    Route::get('', [CalendarEventController::class,'index'])->name('index');
    Route::get('create', [CalendarEventController::class,'create'])->name('create');
    Route::post('', [CalendarEventController::class,'store'])->name('store');
    Route::get('{event}/edit', [CalendarEventController::class,'edit'])->name('edit');
    Route::put('{event}', [CalendarEventController::class,'update'])->name('update');
    Route::delete('{event}', [CalendarEventController::class,'destroy'])->name('destroy');
    Route::get('import', [CalendarEventController::class,'importForm'])->name('importForm');
    Route::post('import', [CalendarEventController::class,'import'])->name('import');
});

/* ========================
   レコードタイプ
======================== */
Route::resource('record_types', RecordTypeController::class);
Route::post('/pro_bowlers/{bowler}/titles', [ProBowlerTitleController::class, 'store'])->name('pro_bowler_titles.store');
Route::delete('/pro_bowlers/{bowler}/titles/{title}', [ProBowlerTitleController::class, 'destroy'])->name('pro_bowler_titles.destroy');

/* 大会成績ページからの「タイトル反映」ボタン（別Controller版） */
Route::post('/tournaments/{tournament}/sync-titles', [TitleSyncController::class, 'sync'])->name('tournaments.sync_titles');

/* ========================
   API
======================== */
Route::get('/api/pro-bowler-by-license/{licenseNo}', function ($licenseNo) {
    $bowler = ProBowler::where('license_no', $licenseNo)->firstOrFail();
    return response()->json([
        'id' => $bowler->id,
        'name_kanji' => $bowler->name_kanji,
        'license_no' => $bowler->license_no,
    ]);
});
