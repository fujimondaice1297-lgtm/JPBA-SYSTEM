<?php

use App\Models\ProBowler;
use App\Models\ProBowlerTraining;
use App\Models\Tournament;
use App\Models\TrainingSession;
use App\Models\TrainingSessionParticipant;
use App\Models\User;
use App\Services\TournamentEntryEligibilityService;

test('staff can operate a training session from creation through correction and qualification updates', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_admin' => true,
        'account_status' => User::STATUS_ACTIVE,
    ]);
    $attendedBowler = ProBowler::query()->create([
        'license_no' => 'M00009881',
        'name_kanji' => '講習会 受講選手',
        'sex' => 1,
        'is_active' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'missing',
    ]);
    $absentBowler = ProBowler::query()->create([
        'license_no' => 'F00009882',
        'name_kanji' => '講習会 未受講選手',
        'sex' => 2,
        'is_active' => true,
        'member_class' => 'player',
        'can_enter_official_tournament' => true,
        'training_compliance_status' => 'missing',
    ]);

    $this->actingAs($admin)
        ->post(route('tp_registration.sessions.store'), [
            'name' => '2026年度 TP講習会 運用試験',
            'held_on' => '2026-08-31',
            'venue' => 'オンライン',
            'notes' => '自動回帰試験',
        ])
        ->assertSessionHasNoErrors();

    $session = TrainingSession::query()->sole();
    expect($session->status)->toBe(TrainingSession::STATUS_OPEN)
        ->and($session->session_year)->toBe(2026);

    $this->actingAs($admin)
        ->post(route('tp_registration.sessions.participants.add', $session), [
            'license_nos' => "M00009881\nF00009882",
        ])
        ->assertSessionHasNoErrors();

    $participants = $session->participants()->get()->keyBy('pro_bowler_id');
    expect($participants)->toHaveCount(2);

    $this->actingAs($admin)
        ->put(route('tp_registration.sessions.participants.update', $session), [
            'participants' => [
                $participants[$attendedBowler->id]->id => [
                    'attendance_status' => TrainingSessionParticipant::STATUS_ATTENDED,
                    'notes' => '受講確認済み',
                ],
                $participants[$absentBowler->id]->id => [
                    'attendance_status' => TrainingSessionParticipant::STATUS_ABSENT,
                    'notes' => '欠席',
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('tp_registration.sessions.finalize', $session))
        ->assertSessionHasNoErrors();

    $session->refresh();
    $attendedParticipant = $participants[$attendedBowler->id]->refresh();
    $trainingRecord = ProBowlerTraining::query()->findOrFail($attendedParticipant->pro_bowler_training_id);

    expect($session->status)->toBe(TrainingSession::STATUS_COMPLETED)
        ->and($trainingRecord->completed_at?->toDateString())->toBe('2026-08-31')
        ->and($trainingRecord->expires_at?->toDateString())->toBe('2029-08-30')
        ->and($absentBowler->refresh()->training_compliance_status)->toBe('missing');

    $csvResponse = $this->actingAs($admin)
        ->get(route('tp_registration.sessions.export', $session))
        ->assertOk()
        ->assertDownload();
    $csv = $csvResponse->streamedContent();
    expect($csv)->toContain('M00009881')
        ->and($csv)->toContain('F00009882')
        ->and($csv)->toContain('2029-08-30');

    $eligibility = app(TournamentEntryEligibilityService::class);
    expect($eligibility->evaluate($attendedBowler->refresh(), new Tournament([
        'start_date' => '2026-08-30',
    ]))['allowed'])->toBeFalse()
        ->and($eligibility->evaluate($attendedBowler->refresh(), new Tournament([
            'start_date' => '2026-08-31',
        ]))['allowed'])->toBeTrue()
        ->and($eligibility->evaluate($attendedBowler->refresh(), new Tournament([
            'start_date' => '2029-08-31',
        ]))['allowed'])->toBeFalse();

    $this->actingAs($admin)
        ->post(route('tp_registration.sessions.reopen', $session))
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->put(route('tp_registration.sessions.participants.update', $session), [
            'participants' => [
                $attendedParticipant->id => [
                    'attendance_status' => TrainingSessionParticipant::STATUS_ABSENT,
                    'notes' => '出欠訂正',
                ],
                $participants[$absentBowler->id]->id => [
                    'attendance_status' => TrainingSessionParticipant::STATUS_ABSENT,
                    'notes' => '欠席',
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('tp_registration.sessions.finalize', $session))
        ->assertSessionHasNoErrors();

    expect($trainingRecord->refresh()->record_status)->toBe('revoked')
        ->and($attendedBowler->refresh()->training_compliance_status)->toBe('missing')
        ->and($eligibility->evaluate($attendedBowler, new Tournament([
            'start_date' => '2026-08-31',
        ]))['allowed'])->toBeFalse();
});
