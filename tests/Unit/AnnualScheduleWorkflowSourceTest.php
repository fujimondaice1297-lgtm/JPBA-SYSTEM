<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnualScheduleWorkflowSourceTest extends TestCase
{
    #[Test]
    public function official_2026_dataset_covers_all_months_and_pdf_columns(): void
    {
        $data = json_decode(
            (string) file_get_contents(database_path('data/jpba_2026_annual_schedule.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(2026, $data['year']);
        $this->assertSame(range(1, 12), array_values(array_unique(array_column($data['rows'], 'month'))));
        $this->assertGreaterThanOrEqual(60, count($data['rows']));

        $required = ['month', 'date_label'];
        foreach ($data['rows'] as $row) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $row);
            }
        }
    }

    #[Test]
    public function admin_and_public_views_share_the_annual_schedule_columns(): void
    {
        $admin = (string) file_get_contents(resource_path('views/annual_schedules/edit.blade.php'));
        $public = (string) file_get_contents(resource_path('views/public/schedule.blade.php'));
        $pdf = (string) file_get_contents(resource_path('views/annual_schedules/pdf.blade.php'));

        foreach (['日（曜日）', 'トーナメント名', '出場資格', 'ポイント', 'AVG', '賞金', '公式'] as $label) {
            $this->assertStringContainsString($label, $admin);
            $this->assertStringContainsString($label, $public);
            $this->assertStringContainsString($label, $pdf);
        }
    }

    #[Test]
    public function tournament_form_requires_an_explicit_duplicate_policy(): void
    {
        $controller = (string) file_get_contents(app_path('Http/Controllers/TournamentController.php'));
        $create = (string) file_get_contents(resource_path('views/tournaments/create.blade.php'));

        $this->assertStringContainsString('assertNoUnresolvedConflict', $controller);
        $this->assertStringContainsString('annual_schedule_conflict_action', $create);
        $this->assertStringContainsString("'link'=>'既存行に紐づける", $create);
        $this->assertStringContainsString("'overwrite'=>'既存行を大会情報で上書き", $create);
        $this->assertStringContainsString("'separate'=>'別の行として追加", $create);
    }
}
