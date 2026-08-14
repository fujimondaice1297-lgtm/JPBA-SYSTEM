<?php

namespace Tests\Unit;

use App\Models\Tournament;
use App\Models\UsedBall;
use App\Services\BallInspectionService;
use Tests\TestCase;

class BallInspectionWorkflowTest extends TestCase
{
    public function test_expiry_is_one_year_minus_one_day_from_the_inspection_date(): void
    {
        $service = new BallInspectionService();

        $this->assertSame(
            '2027-07-30',
            $service->expiresOn('2026-07-31')?->format('Y-m-d')
        );
    }

    public function test_status_distinguishes_provisional_expired_expiring_soon_and_valid(): void
    {
        $service = new BallInspectionService();
        $reference = '2026-08-11';

        $this->assertSame('provisional', $service->status(null, null, $reference)['key']);
        $this->assertSame('expired', $service->status('CERT-1', '2026-08-10', $reference)['key']);
        $this->assertSame('expiring_soon', $service->status('CERT-1', '2026-08-31', $reference)['key']);
        $this->assertSame('valid', $service->status('CERT-1', '2026-10-01', $reference)['key']);
    }

    public function test_required_tournament_uses_the_tournament_start_date_for_eligibility(): void
    {
        $service = new BallInspectionService();
        $tournament = new Tournament(['start_date' => '2026-10-01']);

        $expiredAtTournament = new UsedBall([
            'inspection_number' => 'CERT-1',
            'registered_at' => '2025-10-01',
            'expires_at' => '2026-09-30',
        ]);
        $validAtTournament = new UsedBall([
            'inspection_number' => 'CERT-2',
            'registered_at' => '2026-01-01',
            'expires_at' => '2026-10-01',
        ]);

        $this->assertFalse(
            $service->tournamentEligibility($expiredAtTournament, $tournament)['allowed']
        );
        $this->assertTrue(
            $service->tournamentEligibility($validAtTournament, $tournament)['allowed']
        );
    }

    public function test_tournament_and_renewal_screens_use_the_shared_inspection_rules(): void
    {
        $entryController = file_get_contents(
            app_path('Http/Controllers/TournamentEntryBallController.php')
        );
        $usedBallController = file_get_contents(
            app_path('Http/Controllers/UsedBallController.php')
        );
        $entryView = file_get_contents(
            resource_path('views/member/entry_balls_edit.blade.php')
        );
        $usedBallEdit = file_get_contents(
            resource_path('views/used_balls/edit.blade.php')
        );

        $this->assertIsString($entryController);
        $this->assertStringContainsString('tournamentEligibility', $entryController);
        $this->assertStringContainsString('$isNewSelection && $inspectionRequired', $entryController);
        $this->assertStringNotContainsString(
            '$usedBall->expires_at->lt(now()->startOfDay())',
            $entryController
        );

        $this->assertIsString($usedBallController);
        $this->assertStringContainsString('required_with:inspection_number', $usedBallController);
        $this->assertStringContainsString('syncInspectionToRegisteredBall', $usedBallController);
        $this->assertStringContainsString('expiresOn($inspectionDate)', $usedBallController);

        $this->assertIsString($entryView);
        $this->assertStringContainsString('大会開催日', $entryView);
        $this->assertStringContainsString('大会使用不可', $entryView);
        $this->assertStringContainsString('$inspectionStatuses', $entryView);

        $this->assertIsString($usedBallEdit);
        $this->assertStringContainsString('name="registered_at"', $usedBallEdit);
        $this->assertStringContainsString('再検量時は検量日を新しい日付へ変更', $usedBallEdit);
    }
}
