<?php

// ===== 先頭へ移動（理由：PHPのuseは冒頭のみ有効。途中配置は構文上不可） =====
use Illuminate\Support\Facades\Route;
use App\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use App\Models\ProBowler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\VenuePageController;

use App\Http\Controllers\{
    ProBowlerController, TournamentController, TournamentResultController, RecordTypeController,
    InstructorController, PrizeDistributionController, PointDistributionController,
    ApprovedBallController, ApprovedBallImportController, UsedBallController, ProfileController,
    TournamentProController, TpRegistrationController, RankingController, PerfectRecordController,
    ProGroupController, CertificateController, RegisteredBallController, ComplianceController,
    ProBowlerTrainingController, BulkTrainingController, TrainingReportController,
    CalendarController, CalendarEventController, ProBowlerTitleController, TitleSyncController,
    MemberDashboardController, InformationController, TournamentEntryBallController,
    TournamentEntryController, DrawController, ProBowlerImportController, HofController, HofManageController,
    AuthController, ScoreController, EligibilityController, PublicProfileController, FlashNewsController,
    FlashNewsPublicController
};

// ★LOCAL ONLY: ルート/設定キャッシュ & DB可視化 & ストレージ公開（診断用）BEGIN
if (app()->environment('local')) {

    // 1) まとめてキャッシュ掃除
    Route::get('/_dev/clear', function () {
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        return response()->json(['ok' => true, 'msg' => 'routes/config/cache/view cleared']);
    });

    // 2) テーブル一覧
    Route::get('/_dev/tables', function () {
        $schema = DB::selectOne("SELECT current_schema() AS s")->s ?? 'public';
        $rows = DB::select(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = current_schema()
             ORDER BY table_name"
        );
        return response()->json([
            'schema' => $schema,
            'tables' => array_map(fn($r) => $r->table_name, $rows),
        ]);
    });

    // 3) カラム一覧
    Route::get('/_dev/columns/{table}', function (string $table) {
        if (!Schema::hasTable($table)) {
            return response()->json(['error' => "table '{$table}' not found in current schema"], 404);
        }
        $cols = DB::select(
            "SELECT column_name, data_type
               FROM information_schema.columns
              WHERE table_schema = current_schema()
                AND table_name = ?
              ORDER BY ordinal_position",
            [$table]
        );
        return response()->json(['table' => $table, 'columns' => $cols]);
    });

    // 4) storage:link を試す（成功すれば /public/storage はシンボリックリンク）
    Route::get('/_dev/storage/link', function () {
        $ok = false; $err = null;
        try {
            Artisan::call('storage:link');
            $ok = true;
        } catch (\Throwable $e) {
            $err = $e->getMessage();
        }
        return response()->json([
            'ok' => $ok,
            'exists' => is_link(public_path('storage')) || is_dir(public_path('storage')),
            'public_storage_path' => public_path('storage'),
            'disk_root' => storage_path('app/public'),
            'error' => $err,
        ]);
    });

    // 5) リンク不可環境：/storage に実体コピー（簡易公開）
    Route::get('/_dev/storage/publish', function () {
        $src = storage_path('app/public');
        $dst = public_path('storage');

        if (!is_dir($src)) {
            return response()->json(['ok'=>false,'error'=>"source not found: {$src}"], 500);
        }
        if (!is_dir($dst)) {
            @mkdir($dst, 0775, true);
        }

        $copied = 0; $dirs = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = str_replace('\\','/', substr($item->getPathname(), strlen($src) + 1));
            $to  = $dst . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                if (!is_dir($to)) { @mkdir($to, 0775, true); $dirs++; }
            } else {
                @copy($item->getPathname(), $to);
                $copied++;
            }
        }

        return response()->json([
            'ok'=>true,
            'mode'=>'copy',
            'public_storage_path'=>$dst,
            'disk_root'=>$src,
            'copied_files'=>$copied,
            'created_dirs'=>$dirs,
            'note'=>'Windows/OneDrive等で symlink が作れない場合の暫定公開。ファイル追加後は再実行して同期。'
        ]);
    });

    // 6) 現状確認
    Route::get('/_dev/storage/status', function () {
        return response()->json([
            'public_storage_exists' => is_link(public_path('storage')) || is_dir(public_path('storage')),
            'public_storage_path'   => public_path('storage'),
            'disk_root'             => storage_path('app/public'),
            'public_url_prefix'     => url('/storage'),
        ]);
    });
}
// ★LOCAL ONLY END

/* ========================
   一時デバッグ（確認後に削除）
======================== */
Route::get('/__debug/mw', function () {
    $k = app(HttpKernel::class);
    $ref = new \ReflectionClass($k);
    $prop = $ref->getProperty('middlewareAliases');
    $prop->setAccessible(true);
    return response()->json($prop->getValue($k));
});
Route::get('/__debug/router', fn() => response()->json(app('router')->getMiddleware()));
// 認証済みの自分を確認
Route::middleware('auth')->get('/__debug/me', function () {
    $u = auth()->user();
    return response()->json(['id'=>$u?->id,'email'=>$u?->email,'role'=>$u?->role]);
});
// RoleMiddleware が通るかワンピン
Route::middleware(['auth','role:member,editor,admin'])
    ->get('/__debug/ping', fn() => 'role-ok');

/* ========================
   トップ
======================== */
Route::get('/', fn () => view('welcome'));

/* ========================
   認証（公開）
======================== */
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store']);

Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');

/* =======================================================================
   会員・編集者・管理者 共通（閲覧/自分の操作）  auth + role:member,editor,admin
======================================================================= */
Route::middleware(['auth','role:member,editor,admin'])->group(function () {

    // マイページ・自分のプロフィール
    Route::get('/member', [MemberDashboardController::class,'index'])->name('member.dashboard');
    Route::get('/password/change', [ChangePasswordController::class, 'showForm'])->name('password.change.form');
    Route::post('/password/change', [ChangePasswordController::class, 'update'])->name('password.update.self');
    Route::get('/athlete', [ProBowlerController::class, 'editSelf'])->name('athlete.edit');
    Route::put('/athlete/{bowler}', [ProBowlerController::class, 'updateSelf'])->name('athlete.update');

    // 大会エントリー（会員本人の操作）
    Route::get('/entry/select', [TournamentEntryController::class, 'select'])->name('tournament.entry.select');
    Route::post('/entry/select', [TournamentEntryController::class, 'storeSelection'])->name('tournament.entry.select.store');
    Route::get('/member/entries/{entry}/balls', [TournamentEntryBallController::class, 'edit'])->name('member.entries.balls.edit');
    Route::post('/member/entries/{entry}/balls', [TournamentEntryBallController::class, 'bulkStore'])->name('member.entries.balls.store');
    Route::post('/member/entries/{entry}/shift-draw', [DrawController::class, 'shift'])->name('member.entries.shift.draw');
    Route::post('/member/entries/{entry}/lane-draw', [DrawController::class, 'lane'])->name('member.entries.lane.draw');

    // 使用ボール / 登録ボール（※ Controller 側で member は自分の分だけに絞り込み済み）
    Route::resource('used_balls', UsedBallController::class)->except(['show','destroy']);
    Route::resource('registered_balls', RegisteredBallController::class)->except(['show','destroy']);

    // サイト内の閲覧系
    Route::get('/tournament_pro', [TournamentProController::class, 'index'])->name('tournament_pro.index');
    Route::get('/tp_registration', [TpRegistrationController::class, 'index'])->name('tp_registration.index');
    Route::get('/rankings', [RankingController::class, 'index'])->name('rankings.index');
    Route::get('/perfect_records', [PerfectRecordController::class, 'index'])->name('perfect_records.index');
    Route::get('/pro_groups', [ProGroupController::class, 'index'])->name('pro_groups.index');
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/member/info', [InformationController::class,'member'])->name('informations.member');
    Route::get('/info', [InformationController::class,'index'])->name('informations.index');

    // 大会成績（閲覧）
    Route::get('/tournament_results', [TournamentResultController::class, 'list'])->name('tournament_results.index');
    Route::get('/tournament_results/rankings', [TournamentResultController::class, 'rankings'])->name('tournament_results.rankings');
    Route::get('/tournament_results/pdf', [TournamentResultController::class, 'exportPdf'])->name('tournament_results.pdf');

    // カレンダー（閲覧）
    Route::get('/calendar/{year?}', [CalendarController::class,'annual'])->whereNumber('year')->name('calendar.annual');
    Route::get('/calendar/{year}/{month}', [CalendarController::class,'monthly'])->whereNumber('year')->whereNumber('month')->name('calendar.monthly');
    Route::get('/calendar/{year}/pdf', [CalendarController::class,'annualPdf'])->whereNumber('year')->name('calendar.annual.pdf');
    Route::get('/calendar/{year}/{month}/pdf', [CalendarController::class,'monthlyPdf'])->whereNumber('year')->name('calendar.monthly.pdf');

    Route::get('/flash-news/{id}', [FlashNewsPublicController::class, 'show'])
        ->whereNumber('id')   // ← これがポイント（数値以外はマッチしない）
        ->name('flash_news.public');
    
    // API（会員以上のみで使う想定：route:list のエントリと一致）
    Route::get('/api/pro-bowler-by-license/{licenseNo}', function ($licenseNo) {
        $bowler = ProBowler::where('license_no', $licenseNo)->firstOrFail();
        return response()->json([
            'id' => $bowler->id,
            'name_kanji' => $bowler->name_kanji,
            'license_no' => $bowler->license_no,
        ]);
    })->name('api.pro_bowler_by_license');
});

/* =======================================================================
   編集者 + 管理者（作成/更新は可、削除は不可） auth + role:editor,admin
======================================================================= */
Route::middleware(['auth','role:editor,admin'])->group(function () {

    Route::get('/scores/input', [ScoreController::class, 'input'])->name('scores.input');
    Route::post('/scores/store', [ScoreController::class, 'store']);
    Route::post('/scores/settings/bulk', [ScoreController::class, 'saveSettingBulk']);
    Route::post('/scores/settings/save', [ScoreController::class, 'saveSetting']); // 単品（互換）
    Route::post('/scores/clear-all', [ScoreController::class, 'clearAll']);       // ★ 追加：全クリア
    Route::post('/scores/clear-game', [ScoreController::class, 'clearGame']);     // ★ 追加：該当Gクリア
    Route::get('/scores/result', [ScoreController::class, 'result']);
    Route::get('/scores/board', [ScoreController::class, 'board']);
    Route::get('/scores/api/existing-ids', [ScoreController::class, 'apiExistingIds']);   // ★追加：検証用API
    Route::post('/scores/update-one', [ScoreController::class, 'updateOne']);            // ★追加：個別更新
    Route::post('/scores/delete-one', [ScoreController::class, 'deleteOne']);            // ★追加：個別削除

    // 組織マスタ（名称+URL） ※会場と同様の管理
    Route::resource('organizations', \App\Http\Controllers\OrganizationMasterController::class)->except(['show']);

    // API（検索/1件取得）※tournaments/create の主催/協賛 検索UIが利用
    Route::get('/api/organizations/search', [\App\Http\Controllers\OrganizationMasterController::class,'search'])
        ->name('api.organizations.search');
    Route::get('/api/organizations/{id}', [\App\Http\Controllers\OrganizationMasterController::class,'show'])
        ->name('api.organizations.show');

    // 大会コピー作成
    Route::get('/tournaments/{tournament}/clone', [\App\Http\Controllers\TournamentController::class,'clone'])
        ->name('tournaments.clone');
    
    // 大会マスタ（削除以外）
    Route::resource('tournaments', TournamentController::class)->except(['destroy']);
    // 用語：リソースルート（index/create/store/edit/update/destroy等をまとめて定義する書き方）
    Route::resource('venues', VenuePageController::class)->except(['show']);

    // 大会成績：ネスト資源（index/create/store/edit/update）+ shallow
    Route::resource('tournaments.results', TournamentResultController::class)
        ->only(['index','create','store','edit','update'])
        ->shallow();

    // ====== 旧ルート名（ビュー後方互換）======
    Route::get('/tournaments/{tournament}/results/create', [TournamentResultController::class, 'create'])
        ->name('tournament_results.create');
    Route::post('/tournaments/{tournament}/results', [TournamentResultController::class, 'store'])
        ->name('tournament_results.store');

    // shallow 側の edit/update にも “tournament_results.*” の別名を提供（route:list と一致）
    Route::get('/tournament_results/{result}/edit', [TournamentResultController::class, 'edit'])
        ->name('tournament_results.edit');
    Route::put('/tournament_results/{result}', [TournamentResultController::class, 'update'])
        ->name('tournament_results.update');

    // 一括登録（旧名そのまま）
    Route::get('/tournament_results/batch-create', [TournamentResultController::class, 'batchCreate'])
        ->name('tournament_results.batchCreate');
    Route::post('/tournament_results/batch-store', [TournamentResultController::class, 'batchStore'])
        ->name('tournament_results.batchStore');

    // 追加アクション
    Route::post('/tournaments/{tournament}/results/apply-awards-points',
        [TournamentResultController::class, 'applyAwardsAndPoints'])
        ->name('tournaments.results.apply_awards_points');
    Route::post('/tournaments/{tournament}/results/sync-titles',
        [TournamentResultController::class, 'syncTitles'])
        ->name('tournaments.results.sync');

    // 配分マスタ（削除以外）
    Route::prefix('tournaments/{tournament}')->name('tournaments.')->group(function () {
        Route::resource('prize_distributions', PrizeDistributionController::class)->except(['destroy']);
        Route::resource('point_distributions', PointDistributionController::class)->except(['destroy']);
    });

    // 承認ボール（削除以外）
    Route::get('/approved_balls/import', [ApprovedBallImportController::class, 'showImportForm'])
        ->name('approved_balls.import_form');
    Route::post('/approved_balls/import', [ApprovedBallImportController::class, 'import'])
        ->name('approved_balls.import');
    Route::post('/approved_balls/store-multiple', [ApprovedBallController::class, 'storeMultiple'])
        ->name('approved_balls.store_multiple');
    Route::resource('approved_balls', ApprovedBallController::class)
        ->except(['destroy', 'show']);

    // プロボウラー（編集系＝公開しない）
    Route::get('/athletes', [ProBowlerController::class, 'index'])->name('athlete.index');
    Route::get('/pro_bowlers', [ProBowlerController::class, 'index'])->name('pro_bowlers.index');
    Route::get('/pro-bowlers/create', [ProBowlerController::class, 'create'])->name('pro_bowlers.create');
    Route::post('/pro-bowlers', [ProBowlerController::class, 'store'])->name('pro_bowlers.store');
    Route::get('/pro_bowlers/list', [ProBowlerController::class, 'list'])->name('pro_bowlers.list');
    Route::get('/pro_bowlers/{id}/edit', [ProBowlerController::class, 'edit'])->name('pro_bowlers.edit');
    Route::put('/pro_bowlers/{id}', [ProBowlerController::class, 'update'])->name('pro_bowlers.update');
    Route::get('/pro_bowlers/form', fn () => view('pro_bowlers.athlete_form'))->name('athletes.create');
    Route::post('/athletes/store', fn () => '登録完了（仮）')->name('athletes.store');

    // インストラクター（削除以外）
    Route::resource('instructors', InstructorController::class)->except(['destroy']);
    Route::get('/instructors/export-pdf', [InstructorController::class, 'exportPdf'])->name('instructors.exportPdf');
    Route::get('/instructors/license/{license_no}/edit', [InstructorController::class, 'edit'])->name('instructors.edit_by_license');
    Route::get('/instructors/{license_no}/edit', function (string $license_no) {
        if (preg_match('/^[A-Za-z].*/', $license_no)) {
            return redirect()->route('instructors.edit_by_license', ['license_no' => $license_no], 301);
        }
        abort(404);
    })->name('instructors.edit.legacy');
    Route::get('/certified_instructors/{license_no}/edit', [InstructorController::class, 'edit'])->name('certified_instructors.edit');

    // 訓練系（事務用）
    Route::get('/admin/trainings/bulk',  [BulkTrainingController::class, 'create'])->name('trainings.bulk');
    Route::post('/admin/trainings/bulk', [BulkTrainingController::class, 'store'])->name('trainings.bulk.store');
    Route::get('/admin/trainings/reports/{scope?}', [TrainingReportController::class, 'index'])
        ->whereIn('scope', ['compliant','missing','expired','expiring'])
        ->name('trainings.reports');

    // カレンダーイベント（作成/更新）
    Route::prefix('calendar-events')->name('calendar_events.')->group(function () {
        Route::get('', [CalendarEventController::class,'index'])->name('index');
        Route::get('create', [CalendarEventController::class,'create'])->name('create');
        Route::post('', [CalendarEventController::class,'store'])->name('store');
        Route::get('{event}/edit', [CalendarEventController::class,'edit'])->name('edit');
        Route::put('{event}', [CalendarEventController::class,'update'])->name('update');
        Route::get('import', [CalendarEventController::class,'importForm'])->name('importForm');
        Route::post('import', [CalendarEventController::class,'import'])->name('import');
    });

    // レコードタイプ（削除以外）
    Route::resource('record_types', RecordTypeController::class)->except(['destroy']);

    // タイトル付与（作成）
    Route::post('/pro_bowlers/{bowler}/titles', [ProBowlerTitleController::class, 'store'])->name('pro_bowler_titles.store');

    // タイトル反映（同期）
    Route::post('/tournaments/{tournament}/sync-titles', [TitleSyncController::class, 'sync'])->name('tournaments.sync_titles');

    // プロボウラー取り込み
    Route::get('/pro_bowlers/import', [ProBowlerImportController::class, 'form'])->name('pro_bowlers.import_form');
    Route::post('/pro_bowlers/import', [ProBowlerImportController::class, 'import'])->name('pro_bowlers.import');

    // プログループ管理
    Route::resource('pro_groups', \App\Http\Controllers\ProGroupController::class)
        ->only(['index','show','create','store','edit','update']);
    Route::post('pro_groups/{pro_group}/rebuild', [\App\Http\Controllers\ProGroupController::class,'rebuild'])
        ->name('pro_groups.rebuild');
    Route::get('pro_groups/{pro_group}/export-csv', [\App\Http\Controllers\ProGroupController::class,'exportCsv'])
        ->name('pro_groups.export_csv');
    
    // 大会参加者グループをワンクリック作成
    Route::post('tournaments/{tournament}/participant-group',
        [\App\Http\Controllers\ProGroupController::class, 'quickCreateTournamentGroup']
    )->name('tournaments.participant_group.create');

    // グループメール
    Route::get('pro_groups/{group}/mail/create', [\App\Http\Controllers\GroupMailController::class,'create'])
        ->name('pro_groups.mail.create');
    Route::post('pro_groups/{group}/mail', [\App\Http\Controllers\GroupMailController::class,'store'])
        ->name('pro_groups.mail.store');
    Route::get('pro_groups/{group}/mail/{mailout}', [\App\Http\Controllers\GroupMailController::class,'show'])
        ->name('pro_groups.mail.show');
    
    // ===== 殿堂 管理（作成/更新のみ。削除なし） =====
    Route::get('/hof/create', [HofManageController::class, 'create'])->name('hof.create');   // ← 先に定義
    Route::post('/hof',       [HofManageController::class, 'store'])->name('hof.store');

    Route::get('/hof/{id}/edit', [HofManageController::class, 'edit'])
        ->whereNumber('id')->name('hof.edit');
    Route::put('/hof/{id}',      [HofManageController::class, 'update'])
        ->whereNumber('id')->name('hof.update');

    Route::post('/hof/{id}/photos/upload', [HofManageController::class, 'uploadPhoto'])
        ->whereNumber('id')->name('hof.photos.upload');

    // （URL直貼り追加を残している場合のみ）
    // Route::post('/hof/{id}/photos/url', [HofManageController::class, 'addPhotoUrl'])
    //     ->whereNumber('id')->name('hof.photos.url');

    // ===== 殿堂 閲覧（一覧・詳細） =====
    Route::get('/hof', [HofController::class, 'index'])->name('hof.index');

    // 予約語「create」を slug から除外して衝突回避
    Route::get('/hof/{slug}', [HofController::class, 'show'])
        ->where('slug', '^(?!create$)[A-Za-z0-9\-_]+$')
        ->name('hof.show');

    // 便利ショートカット：slug から殿堂レコードIDを解決して編集へリダイレクト
    Route::get('/hof/{slug}/manage', function (string $slug) {
        $T   = env('JPBA_PROFILES_TABLE');
        $CID = env('JPBA_PROFILES_ID_COL','id');
        $CSL = env('JPBA_PROFILES_SLUG_COL','slug');

        $pro = DB::table($T)->where($CSL,$slug)->first([$CID.' as id']);
        if (!$pro) abort(404);

        $hof = DB::table('hof_inductions')->where('pro_id',$pro->id)->first(['id']);
        if (!$hof) abort(404);

        return redirect()->route('hof.edit',['id'=>$hof->id]);
    })->where('slug','^(?!create$)[A-Za-z0-9\-_]+$')->name('hof.manage.by_slug');

    /* === 資格ページ（永久シード / A級ライセンス） === */
    Route::prefix('eligibility')->name('eligibility.')->group(function () {
        // 永久シード
        Route::get('/evergreen', [EligibilityController::class, 'evergreen'])
            ->name('evergreen');

        // A級ライセンス（性別は m / f の2値のみ許可）
        Route::get('/a-class/m', [EligibilityController::class, 'aClassMen'])->name('a_class.m');
        Route::get('/a-class/f', [EligibilityController::class, 'aClassWomen'])->name('a_class.f');
    });

    Route::get('/flash-news', [FlashNewsController::class, 'index'])->name('flash_news.index');
    Route::get('/flash-news/create', [FlashNewsController::class, 'create'])->name('flash_news.create');
    Route::post('/flash-news', [FlashNewsController::class, 'store'])->name('flash_news.store');
    Route::get('/flash-news/{id}/edit', [FlashNewsController::class, 'edit'])->name('flash_news.edit');
    Route::put('/flash-news/{id}', [FlashNewsController::class, 'update'])->name('flash_news.update');

    /* === プロボウラー公開プロフィール === */
    Route::get('/pro_bowlers/{id}', [PublicProfileController::class, 'show'])
        ->name('pro_bowlers.public_show');

    // 個別保存（必要なら）
    Route::post('/pro_bowlers/{pro_bowler}/trainings', [ProBowlerTrainingController::class, 'store'])->name('pro_bowler_trainings.store');
});

/* =======================================================================
   管理者のみ（削除・設定・通知） auth + role:admin
======================================================================= */
Route::prefix('admin')->name('admin.')
    ->middleware(['auth','role:admin'])
    ->group(function () {
        Route::get('/', [AdminHomeController::class, 'index'])->name('home');

        Route::delete('/hof/photos/{photo}', [HofManageController::class, 'destroyPhoto'])
        ->whereNumber('photo')
        ->name('hof.photos.destroy');

        // ★殿堂レコード本体の削除（写真も巻き取り）※管理者のみ
        Route::delete('/hof/{id}', [HofManageController::class, 'destroy'])
            ->whereNumber('id')
            ->name('hof.destroy');
        
        // 抽選設定
        Route::get('/tournaments/{tournament}/draw-settings', [DrawController::class, 'settings'])->name('tournaments.draw.settings');
        Route::post('/tournaments/{tournament}/draw-settings', [DrawController::class, 'saveSettings'])->name('tournaments.draw.settings.save');

        // コンプライアンス
        Route::get('/compliance', [ComplianceController::class,'index'])->name('compliance.index');
        Route::post('/compliance/notify', [ComplianceController::class,'notify'])->name('compliance.notify');

        // --- 削除系をここに集約 ---
        Route::delete('/tournaments/{tournament}', [TournamentController::class,'destroy'])->name('tournaments.destroy');
        Route::delete('/tournaments/{tournament}/results/{result}', [TournamentResultController::class,'destroy'])->name('tournaments.results.destroy');
        Route::delete('/tournaments/{tournament}/prize_distributions/{prize_distribution}', [PrizeDistributionController::class,'destroy'])
            ->name('tournaments.prize_distributions.destroy');
        Route::delete('/tournaments/{tournament}/point_distributions/{point_distribution}', [PointDistributionController::class,'destroy'])
            ->name('tournaments.point_distributions.destroy');

        Route::delete('/approved_balls/{approved_ball}', [ApprovedBallController::class,'destroy'])->name('approved_balls.destroy');
        Route::delete('/registered_balls/{registered_balls}', [RegisteredBallController::class,'destroy'])->name('registered_balls.destroy');
        Route::delete('/used_balls/{used_ball}', [UsedBallController::class,'destroy'])->name('used_balls.destroy');

        Route::delete('/instructors/{instructor}', [InstructorController::class,'destroy'])->name('instructors.destroy');
        Route::delete('/calendar-events/{event}', [CalendarEventController::class,'destroy'])->name('calendar_events.destroy');
        Route::delete('/record_types/{record_type}', [RecordTypeController::class,'destroy'])->name('record_types.destroy');

        // グループ削除（管理者のみ）
        Route::delete('pro_groups/{pro_group}', [\App\Http\Controllers\ProGroupController::class, 'destroy'])
            ->middleware(['auth','role:admin'])
            ->name('pro_groups.destroy');

        Route::delete('/pro_bowlers/{bowler}/titles/{title}', [ProBowlerTitleController::class, 'destroy'])->name('pro_bowler_titles.destroy');
        
        // 全テーブル一覧（現在のスキーマ）
        Route::get('/tools/db/tables', function () {
            $tables = DB::select("SELECT table_name
                                FROM information_schema.tables
                                WHERE table_schema = current_schema()
                                ORDER BY table_name");
            return response()->json([
                'schema' => DB::selectOne("SELECT current_schema() AS s")->s ?? 'public',
                'tables' => array_map(fn($r) => $r->table_name, $tables),
            ]);
        })->middleware(['auth','role:admin'])->name('tools.db.tables');

        // 任意テーブルのカラム一覧
        Route::get('/tools/db/columns/{table}', function (string $table) {
            if (!Schema::hasTable($table)) {
                return response()->json(['error' => "table '{$table}' not found in current schema"], 404);
            }
            $cols = DB::select("SELECT column_name, data_type
                                FROM information_schema.columns
                                WHERE table_schema = current_schema()
                                AND table_name = ?
                                ORDER BY ordinal_position", [$table]);
            return response()->json([
                'table' => $table,
                'columns' => $cols,
            ]);
        })->middleware(['auth','role:admin'])->name('tools.db.columns');
        // ★DBテーブル/カラム診断（管理者専用・一時用）END
    
    });
