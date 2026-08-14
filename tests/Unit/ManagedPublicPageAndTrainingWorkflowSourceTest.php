<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ManagedPublicPageAndTrainingWorkflowSourceTest extends TestCase
{
    public function test_editable_public_pages_have_public_and_admin_routes(): void
    {
        foreach ([
            'public.managed_pages.show',
            'admin.public_pages.index',
            'admin.public_pages.create',
            'admin.public_pages.store',
            'admin.public_pages.edit',
            'admin.public_pages.update',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "未定義ルート: {$routeName}");
        }

        $editor = file_get_contents(resource_path('views/admin/public_pages/edit.blade.php'));
        $this->assertStringContainsString('contenteditable="true"', $editor);
        $this->assertStringContainsString('一般公開する', $editor);
        $this->assertStringContainsString('移行元の記録', $editor);
    }

    public function test_tp_training_workflow_covers_attendance_expiry_notification_and_entry_eligibility(): void
    {
        foreach ([
            'tp_registration.index',
            'tp_registration.sessions.store',
            'tp_registration.sessions.participants.add',
            'tp_registration.sessions.participants.update',
            'tp_registration.sessions.finalize',
            'tp_registration.sessions.export',
            'admin.compliance.index',
            'admin.compliance.sync_official_list',
            'admin.compliance.notify',
            'admin.compliance.reconcile',
            'admin.compliance.export',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "未定義ルート: {$routeName}");
        }

        $compliance = file_get_contents(app_path('Services/TrainingComplianceService.php'));
        $notification = file_get_contents(app_path('Services/TrainingExpiryNotificationService.php'));
        $officialImport = file_get_contents(app_path('Services/TrainingOfficialListImportService.php'));
        $entry = file_get_contents(app_path('Services/TournamentEntryEligibilityService.php'));
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString('addMonthsNoOverflow', $compliance);
        $this->assertStringContainsString('subDay()', $compliance);
        $this->assertStringContainsString('expiry_previous_year', $notification);
        $this->assertStringContainsString('source_sha256', $officialImport);
        $this->assertStringContainsString('official_list_valid', $compliance);
        $this->assertStringContainsString('expiredOfficialListEvidenceAt', $compliance);
        $this->assertStringNotContainsString('暫定的に出場可', $compliance);
        $this->assertStringContainsString('entryDecision', $entry);
        $this->assertStringContainsString("training:notify", $consoleRoutes);
        $this->assertStringContainsString("dailyAt('08:00')", $consoleRoutes);
    }

    public function test_player_edit_uses_tp_training_decision_instead_of_instructor_renewal_status(): void
    {
        $form = file_get_contents(resource_path('views/pro_bowlers/athlete_form.blade.php'));
        $widget = file_get_contents(resource_path('views/pro_bowlers/_training_widget.blade.php'));

        $this->assertStringContainsString('TrainingComplianceService::class)->entryDecision($bowler)', $form);
        $this->assertStringContainsString('TournamentEntryEligibilityService::class)->evaluate($bowler)', $form);
        $this->assertStringContainsString('ProBowlerMembershipClassificationService::class)->decide', $form);
        $this->assertStringContainsString('会員種別の自動判定', $form);
        $this->assertStringNotContainsString('TP講習状態', $form);
        $this->assertStringNotContainsString('TP講習有効期限', $form);
        $this->assertStringNotContainsString('現在の大会出場資格', $form);
        $this->assertStringNotContainsString('<strong>更新状態:</strong>', $form);
        $this->assertStringContainsString('インストラクター資格（TP講習会とは別制度）', $form);
        $this->assertStringContainsString('資格更新状態', $form);

        $this->assertStringContainsString('entryDecision($bowler)', $widget);
        $this->assertStringContainsString('TP講習・大会出場資格', $widget);
        $this->assertSame(1, substr_count($widget, 'TP講習状態'));
        $this->assertSame(1, substr_count($widget, 'TP講習有効期限'));
        $this->assertSame(1, substr_count($widget, '現在の大会出場資格'));
        $this->assertStringContainsString("data_get(\$trainingDecision, 'status'", $widget);
        $this->assertStringNotContainsString('$bowler->compliance_status', $widget);
    }

    public function test_membership_type_sync_is_scheduled_for_year_and_result_changes(): void
    {
        $service = file_get_contents(app_path('Services/ProBowlerMembershipClassificationService.php'));
        $searchScope = file_get_contents(app_path('Services/ProBowlerSearchScopeService.php'));
        $command = file_get_contents(app_path('Console/Commands/SyncProBowlerMembershipTypes.php'));
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        foreach (['第１シード', '第２シード', 'トーナメントプロ', '講習会出席者', '海外プロ', 'その他'] as $type) {
            $this->assertStringContainsString($type, $service);
        }
        $this->assertStringContainsString("where('tournament.official_type', 'official')", $service);
        $this->assertStringContainsString("'rank' => (int) \$row->seed_rank", $service);
        $this->assertStringContainsString('pro-bowlers:sync-membership-types', $command);
        $this->assertStringContainsString("Schedule::command('pro-bowlers:sync-membership-types')", $consoleRoutes);
        $this->assertStringContainsString("'海外プロ'", $searchScope);
        $this->assertStringNotContainsString("where('label', '海外')", $searchScope);
    }

    public function test_official_tp_training_dataset_has_expected_publication_counts(): void
    {
        $payload = json_decode(
            file_get_contents(database_path('data/jpba_official_tp_training_25th_20260813.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(25, $payload['edition_number']);
        $this->assertCount(394, $payload['entries']['M']);
        $this->assertCount(249, $payload['entries']['F']);
        $this->assertSame(643, count($payload['entries']['M']) + count($payload['entries']['F']));
        $this->assertSame('official_cycle', $payload['date_precision']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payload['source_sha256']);
    }

    public function test_historical_tp_training_datasets_are_auditable_and_non_current(): void
    {
        $expected = [
            2015 => [365, 194],
            2016 => [65, 30],
            2017 => [41, 30],
            2018 => [347, 203],
            2019 => [66, 16],
            2024 => [396, 253],
        ];

        foreach ($expected as $year => [$male, $female]) {
            $payload = json_decode(
                file_get_contents(database_path("data/jpba_official_tp_training_history_{$year}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $this->assertFalse($payload['is_current']);
            $this->assertTrue($payload['allow_unmatched']);
            $this->assertCount($male, $payload['entries']['M']);
            $this->assertCount($female, $payload['entries']['F']);
            $this->assertSame($male, count(array_unique($payload['entries']['M'])));
            $this->assertSame($female, count(array_unique($payload['entries']['F'])));
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payload['source_sha256']);
        }

        $extractor = file_get_contents(base_path('tools/extract_tp_training_history.py'));
        $this->assertStringContainsString('application list only; not attendance evidence', $extractor);
    }
}
