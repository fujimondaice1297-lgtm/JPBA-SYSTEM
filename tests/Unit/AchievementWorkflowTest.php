<?php

use App\Models\GameScore;
use App\Models\ProBowler;
use App\Models\RecordCertificationSequence;
use App\Models\RecordType;
use App\Models\ScoreSeriesDefinition;
use App\Models\Tournament;
use App\Services\AchievementRecordService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('pdo_sqlite is not installed in this runtime.');
    }

    foreach ([
        'record_types',
        'record_certification_sequences',
        'score_series_definitions',
        'stage_settings',
        'game_scores',
        'tournaments',
        'pro_bowlers',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('pro_bowlers', function (Blueprint $table) {
        $table->id();
        $table->string('license_no')->unique();
        $table->unsignedInteger('license_no_num')->nullable();
        $table->string('name_kanji')->nullable();
        $table->unsignedInteger('perfect_count')->default(0);
        $table->unsignedInteger('eight_hundred_count')->default(0);
        $table->unsignedInteger('seven_ten_count')->default(0);
        $table->unsignedInteger('award_total_count')->default(0);
        $table->timestamps();
    });
    Schema::create('tournaments', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->date('start_date')->nullable();
        $table->timestamps();
    });
    Schema::create('game_scores', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tournament_id');
        $table->unsignedBigInteger('pro_bowler_id')->nullable();
        $table->string('stage');
        $table->string('shift')->nullable();
        $table->char('gender', 1)->nullable();
        $table->string('license_number')->nullable();
        $table->string('name')->nullable();
        $table->unsignedInteger('game_number');
        $table->unsignedInteger('score');
        $table->timestamps();
    });
    Schema::create('stage_settings', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tournament_id');
        $table->string('stage');
        $table->unsignedInteger('total_games');
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });
    Schema::create('score_series_definitions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tournament_id');
        $table->string('stage');
        $table->string('shift')->nullable();
        $table->char('gender', 1)->nullable();
        $table->string('label');
        $table->unsignedInteger('start_game');
        $table->unsignedInteger('end_game');
        $table->boolean('is_800_eligible')->default(true);
        $table->boolean('is_enabled')->default(true);
        $table->string('source')->default('manual');
        $table->timestamps();
    });
    Schema::create('record_certification_sequences', function (Blueprint $table) {
        $table->id();
        $table->string('record_type');
        $table->char('gender', 1);
        $table->unsignedBigInteger('next_number');
        $table->string('prefix')->nullable();
        $table->string('suffix')->nullable();
        $table->boolean('is_enabled')->default(true);
        $table->timestamps();
    });
    Schema::create('record_types', function (Blueprint $table) {
        $table->id();
        $table->string('record_type');
        $table->unsignedBigInteger('pro_bowler_id');
        $table->unsignedBigInteger('tournament_id')->nullable();
        $table->unsignedBigInteger('source_game_score_id')->nullable();
        $table->unsignedBigInteger('score_series_definition_id')->nullable();
        $table->string('tournament_name');
        $table->string('game_numbers')->nullable();
        $table->string('frame_number')->nullable();
        $table->date('awarded_on')->nullable();
        $table->string('certification_number')->nullable();
        $table->unsignedBigInteger('certification_number_value')->nullable();
        $table->string('stage')->nullable();
        $table->string('shift')->nullable();
        $table->char('gender', 1)->nullable();
        $table->string('series_label')->nullable();
        $table->unsignedInteger('series_start_game')->nullable();
        $table->unsignedInteger('series_end_game')->nullable();
        $table->unsignedInteger('series_total')->nullable();
        $table->json('series_scores')->nullable();
        $table->string('status');
        $table->string('registration_mode');
        $table->string('detection_key')->nullable()->unique();
        $table->string('source_type')->nullable();
        $table->text('source_url')->nullable();
        $table->string('source_label')->nullable();
        $table->text('evidence_text')->nullable();
        $table->text('warning')->nullable();
        $table->timestamp('detected_at')->nullable();
        $table->timestamp('confirmed_at')->nullable();
        $table->unsignedBigInteger('confirmed_by')->nullable();
        $table->timestamp('count_applied_at')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    DB::table('pro_bowlers')->insert([
        'id' => 1,
        'license_no' => 'M00001219',
        'license_no_num' => 1219,
        'name_kanji' => '川添奨太',
        'perfect_count' => 25,
        'eight_hundred_count' => 3,
        'seven_ten_count' => 3,
        'award_total_count' => 31,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('tournaments')->insert([
        'id' => 1,
        'name' => 'テスト大会',
        'start_date' => '2026-07-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    RecordCertificationSequence::query()->create([
        'record_type' => 'perfect',
        'gender' => 'M',
        'next_number' => 1800,
        'is_enabled' => true,
    ]);
    RecordCertificationSequence::query()->create([
        'record_type' => 'eight_hundred',
        'gender' => 'M',
        'next_number' => 320,
        'is_enabled' => true,
    ]);
    ScoreSeriesDefinition::query()->create([
        'tournament_id' => 1,
        'stage' => '予選',
        'label' => '予選第1シリーズ',
        'start_game' => 1,
        'end_game' => 3,
        'is_800_eligible' => true,
        'is_enabled' => true,
    ]);
});

it('detects a perfect and only the explicitly defined exact three game 800 series', function () {
    foreach ([1 => 300, 2 => 250, 3 => 251, 4 => 300] as $game => $score) {
        GameScore::query()->create([
            'tournament_id' => 1,
            'pro_bowler_id' => 1,
            'stage' => '予選',
            'game_number' => $game,
            'score' => $score,
        ]);
    }

    expect(RecordType::query()->where('record_type', 'perfect')->count())->toBe(2)
        ->and(RecordType::query()->where('record_type', 'eight_hundred')->count())->toBe(1);

    $series = RecordType::query()->where('record_type', 'eight_hundred')->firstOrFail();
    expect($series->series_start_game)->toBe(1)
        ->and($series->series_end_game)->toBe(3)
        ->and($series->series_total)->toBe(801);
});

it('keeps historical totals unchanged and increments a new achievement exactly once', function () {
    $historical = RecordType::query()->create([
        'record_type' => 'perfect',
        'pro_bowler_id' => 1,
        'tournament_name' => '過去大会',
        'status' => RecordType::STATUS_CANDIDATE,
        'registration_mode' => RecordType::MODE_HISTORICAL,
        'gender' => 'M',
    ]);

    app(AchievementRecordService::class)->confirm($historical);
    expect(ProBowler::query()->findOrFail(1)->perfect_count)->toBe(25);

    $newRecord = RecordType::query()->create([
        'record_type' => 'perfect',
        'pro_bowler_id' => 1,
        'tournament_name' => '新大会',
        'status' => RecordType::STATUS_CANDIDATE,
        'registration_mode' => RecordType::MODE_NEW,
        'gender' => 'M',
    ]);

    $confirmed = app(AchievementRecordService::class)->confirm($newRecord);
    app(AchievementRecordService::class)->confirm($confirmed);

    expect($confirmed->certification_number)->toBe('1801')
        ->and(ProBowler::query()->findOrFail(1)->perfect_count)->toBe(26)
        ->and(RecordCertificationSequence::query()
            ->where('record_type', 'perfect')
            ->where('gender', 'M')
            ->value('next_number'))->toBe(1802);
});

it('never removes a confirmed count when its source score is corrected', function () {
    $score = GameScore::query()->create([
        'tournament_id' => 1,
        'pro_bowler_id' => 1,
        'stage' => '予選',
        'game_number' => 5,
        'score' => 300,
    ]);
    $record = RecordType::query()
        ->where('detection_key', 'score:perfect:' . $score->id)
        ->firstOrFail();
    $record->registration_mode = RecordType::MODE_NEW;
    $record->save();
    app(AchievementRecordService::class)->confirm($record);

    $score->score = 299;
    $score->save();

    $record->refresh();
    expect($record->status)->toBe(RecordType::STATUS_CONFIRMED)
        ->and($record->warning)->not->toBeNull()
        ->and(ProBowler::query()->findOrFail(1)->perfect_count)->toBe(26);
});
