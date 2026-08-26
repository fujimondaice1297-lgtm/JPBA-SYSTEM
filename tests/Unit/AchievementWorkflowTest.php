<?php

use App\Models\GameScore;
use App\Models\ProBowler;
use App\Models\RecordCertificationSequence;
use App\Models\RecordType;
use App\Models\ScoreSeriesDefinition;
use App\Models\TournamentMatchScoreSheet;
use App\Services\AchievementDetectionService;
use App\Services\AchievementRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    DB::table('pro_bowlers')->insert([
        'id' => 1,
        'license_no' => 'M00001219',
        'name_kanji' => '川添奨太',
        'sex' => 1,
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
    RecordCertificationSequence::query()
        ->where('record_type', 'perfect')
        ->where('gender', 'M')
        ->update([
            'next_number' => 1800,
            'is_enabled' => true,
        ]);
    RecordCertificationSequence::query()
        ->where('record_type', 'eight_hundred')
        ->where('gender', 'M')
        ->update([
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
        ->where('detection_key', 'score:perfect:'.$score->id)
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

it('creates a seven ten candidate only from an exact confirmed frame and keeps a confirmed count after correction', function () {
    config(['achievements.cutover_date' => '2026-01-01']);

    $sheet = TournamentMatchScoreSheet::query()->create([
        'tournament_id' => 1,
        'sheet_type' => 'step_ladder',
        'stage_code' => 'ステップラダー',
        'match_label' => '優勝決定戦',
        'game_number' => 1,
        'confirmed_at' => now(),
    ]);
    $player = $sheet->players()->create([
        'sort_order' => 1,
        'player_slot' => 'A',
        'pro_bowler_id' => 1,
        'pro_bowler_license_no' => 'M00001219',
        'display_name' => '川添奨太',
        'final_score' => 200,
    ]);
    $frame = $player->frames()->create([
        'frame_no' => 7,
        'throw1' => '8',
        'throw2' => '/',
        'remaining_pins' => [7, 10],
    ]);

    $summary = app(AchievementDetectionService::class)->scanTournament(1);
    $record = RecordType::query()
        ->where('record_type', 'seven_ten')
        ->where('source_type', 'frame_auto')
        ->firstOrFail();

    expect($summary['seven_ten_candidates'])->toBe(1)
        ->and($record->status)->toBe(RecordType::STATUS_CANDIDATE)
        ->and($record->source_match_score_frame_id)->toBe($frame->id)
        ->and($record->game_numbers)->toBe('ステップラダー 優勝決定戦 1G目')
        ->and($record->frame_number)->toBe('7フレーム目')
        ->and($record->registration_mode)->toBe(RecordType::MODE_NEW);

    app(AchievementRecordService::class)->confirm($record);
    expect(ProBowler::query()->findOrFail(1)->seven_ten_count)->toBe(4);

    $frame->update(['throw2' => '1']);
    app(AchievementDetectionService::class)->scanTournament(1);

    $record->refresh();
    expect($record->status)->toBe(RecordType::STATUS_CONFIRMED)
        ->and($record->warning)->not->toBeNull()
        ->and(ProBowler::query()->findOrFail(1)->seven_ten_count)->toBe(4);

    $frame->update(['throw2' => '/']);
    app(AchievementDetectionService::class)->scanTournament(1);

    expect($record->refresh()->warning)->toBeNull()
        ->and(ProBowler::query()->findOrFail(1)->seven_ten_count)->toBe(4)
        ->and(RecordType::query()->where('record_type', 'seven_ten')->count())->toBe(1);
});

it('rejects incomplete seven ten evidence and supports the tenth frame after a strike', function () {
    $sheet = TournamentMatchScoreSheet::query()->create([
        'tournament_id' => 1,
        'sheet_type' => 'step_ladder',
        'stage_code' => 'ステップラダー',
        'game_number' => 1,
        'confirmed_at' => now(),
    ]);
    $player = $sheet->players()->create([
        'sort_order' => 1,
        'player_slot' => 'A',
        'pro_bowler_id' => 1,
        'pro_bowler_license_no' => 'M00001219',
        'display_name' => '川添奨太',
        'final_score' => 200,
    ]);

    $player->frames()->create([
        'frame_no' => 1,
        'throw1' => '8',
        'throw2' => '1',
        'remaining_pins' => [7, 10],
    ]);
    $player->frames()->create([
        'frame_no' => 2,
        'throw1' => '5',
        'throw2' => '/',
        'remaining_pins' => [4, 6, 7, 9, 10],
    ]);
    $tenth = $player->frames()->create([
        'frame_no' => 10,
        'throw1' => 'X',
        'throw2' => '8',
        'throw3' => '/',
        'remaining_pins' => [7, 10],
    ]);

    $summary = app(AchievementDetectionService::class)->scanTournament(1);

    expect($summary['seven_ten_candidates'])->toBe(1)
        ->and(RecordType::query()->where('record_type', 'seven_ten')->count())->toBe(1)
        ->and(RecordType::query()->where('record_type', 'seven_ten')->value('source_match_score_frame_id'))
        ->toBe($tenth->id);
});
