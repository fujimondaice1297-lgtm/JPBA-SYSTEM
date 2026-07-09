<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ForwardTestDataResetCommand extends Command
{
    private const CONFIRM_TOKEN = 'FORWARD-TEST-RESET';

    protected $signature = 'jpba:forward-test-reset
        {--force : Actually delete data. Without this option, the command is dry-run only}
        {--confirm= : Required token for --force. Use FORWARD-TEST-RESET}
        {--backup-confirmed : Required for --force after DB/storage backups are created}
        {--admin-email= : Admin email to create and preserve during reset}
        {--admin-name=JPBA Admin : Admin display name}
        {--admin-password= : Initial admin password. Required with --force}
        {--include-content : Also clear public information/news tables}
        {--include-pro-test : Also clear pro-test operational tables}
        {--json : Output JSON report}';

    protected $description = 'Preview or execute the JPBA forward-test data reset while preserving code, UI, and a new admin account.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $includeContent = (bool) $this->option('include-content');
        $includeProTest = (bool) $this->option('include-pro-test');
        $adminEmail = trim((string) $this->option('admin-email'));

        try {
            $before = $this->buildReport($adminEmail, $includeContent, $includeProTest);

            if (! $force) {
                $this->renderReport($before + [
                    'mode' => 'dry-run',
                    'message' => '--force is not set. No data was changed.',
                ]);

                return self::SUCCESS;
            }

            $validationError = $this->validateForceOptions($adminEmail);
            if ($validationError !== null) {
                $this->renderReport($before + [
                    'mode' => 'blocked',
                    'message' => $validationError,
                ]);

                return self::FAILURE;
            }

            DB::transaction(function () use ($adminEmail, $includeContent, $includeProTest): void {
                $this->createOrUpdateAdmin($adminEmail);

                $nonAdminUsersDeleted = false;

                foreach ($this->tableGroups($includeContent, $includeProTest) as $key => $group) {
                    if (! $nonAdminUsersDeleted && ! in_array($key, ['auth_runtime', 'communication_and_membership', 'score_imports'], true)) {
                        $this->deleteNonAdminUsers($adminEmail);
                        $nonAdminUsersDeleted = true;
                    }

                    foreach ($group['tables'] as $table) {
                        $this->deleteTable($table);
                    }
                }

                if (! $nonAdminUsersDeleted) {
                    $this->deleteNonAdminUsers($adminEmail);
                }
            });

            $after = $this->buildReport($adminEmail, $includeContent, $includeProTest);
            $this->renderReport($after + [
                'mode' => 'executed',
                'message' => 'Forward-test reset completed.',
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            if (! $this->option('json')) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function validateForceOptions(string $adminEmail): ?string
    {
        if ((string) $this->option('confirm') !== self::CONFIRM_TOKEN) {
            return '--force requires --confirm='.self::CONFIRM_TOKEN;
        }

        if (! $this->option('backup-confirmed')) {
            return '--force requires --backup-confirmed after DB/storage backups are created.';
        }

        if ($adminEmail === '') {
            return '--force requires --admin-email so the reset cannot lock out admin access.';
        }

        if (trim((string) $this->option('admin-password')) === '') {
            return '--force requires --admin-password for the preserved admin account.';
        }

        return null;
    }

    private function buildReport(string $adminEmail, bool $includeContent, bool $includeProTest): array
    {
        $groups = [];
        $missingTables = [];
        $targetRowCount = 0;

        foreach ($this->tableGroups($includeContent, $includeProTest) as $key => $group) {
            $rows = [];
            $groupCount = 0;

            foreach ($group['tables'] as $table) {
                if (! Schema::hasTable($table)) {
                    $rows[$table] = null;
                    $missingTables[] = $table;
                    continue;
                }

                $count = DB::table($table)->count();
                $rows[$table] = $count;
                $groupCount += $count;
            }

            $targetRowCount += $groupCount;
            $groups[$key] = [
                'label' => $group['label'],
                'row_count' => $groupCount,
                'tables' => $rows,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'admin' => $this->adminReport($adminEmail),
            'target_row_count' => $targetRowCount,
            'groups' => $groups,
            'missing_tables' => array_values(array_unique($missingTables)),
            'optional_groups' => [
                'include_content' => $includeContent,
                'include_pro_test' => $includeProTest,
            ],
            'preserved_by_default' => [
                'code',
                'routes',
                'controllers',
                'services',
                'views',
                'commands',
                'migrations',
                'config',
                'master/reference tables',
            ],
        ];
    }

    private function adminReport(string $adminEmail): array
    {
        if (! Schema::hasTable('users')) {
            return [
                'users_table_exists' => false,
                'admin_email' => $adminEmail ?: null,
                'users_total' => 0,
                'users_to_delete' => 0,
                'admin_candidates' => [],
            ];
        }

        $columns = array_values(array_filter(
            ['id', 'name', 'email', 'role', 'is_admin', 'pro_bowler_id', 'pro_bowler_license_no'],
            fn (string $column): bool => Schema::hasColumn('users', $column)
        ));

        $adminCandidates = DB::table('users')
            ->select($columns)
            ->where(function ($query): void {
                if (Schema::hasColumn('users', 'role')) {
                    $query->where('role', 'admin');
                }

                if (Schema::hasColumn('users', 'is_admin')) {
                    $query->orWhere('is_admin', true);
                }
            })
            ->orderBy('id')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        $usersToDelete = DB::table('users')->count();
        if ($adminEmail !== '') {
            $usersToDelete = DB::table('users')
                ->where(function ($query) use ($adminEmail): void {
                    $query->where('email', '<>', $adminEmail)
                        ->orWhereNull('email');
                })
                ->count();
        }

        return [
            'users_table_exists' => true,
            'admin_email' => $adminEmail ?: null,
            'users_total' => DB::table('users')->count(),
            'users_to_delete' => $usersToDelete,
            'admin_candidates' => $adminCandidates,
            'force_requirements' => [
                '--admin-email',
                '--admin-password',
                '--backup-confirmed',
                '--confirm='.self::CONFIRM_TOKEN,
            ],
        ];
    }

    private function tableGroups(bool $includeContent, bool $includeProTest): array
    {
        $groups = [
            'auth_runtime' => [
                'label' => 'Auth sessions, password resets, and API tokens',
                'tables' => [
                    'sessions',
                    'password_reset_tokens',
                    'personal_access_tokens',
                ],
            ],
            'communication_and_membership' => [
                'label' => 'Member communication/history data tied to bowlers/users',
                'tables' => [
                    'group_mail_recipients',
                    'group_mailouts',
                    'group_members',
                    'annual_dues',
                ],
            ],
            'score_imports' => [
                'label' => 'Score import batches, rows, candidates, and operation logs',
                'tables' => [
                    'score_import_operation_logs',
                    'score_import_row_candidates',
                    'score_import_rows',
                    'score_import_batches',
                ],
            ],
            'match_score_sheets' => [
                'label' => 'Frame-by-frame match score sheet input data',
                'tables' => [
                    'tournament_match_score_frames',
                    'tournament_match_score_sheet_players',
                    'tournament_match_score_sheets',
                ],
            ],
            'tournament_snapshots_and_entries' => [
                'label' => 'Tournament snapshots, entries, lane assignments, and logs',
                'tables' => [
                    'tournament_result_snapshot_rows',
                    'tournament_result_snapshots',
                    'tournament_entry_operation_logs',
                    'tournament_entry_balls',
                    'tournament_entries',
                    'tournament_round_lane_assignments',
                    'tournament_draw_reminder_logs',
                    'tournament_auto_draw_logs',
                ],
            ],
            'rankings_and_seeds' => [
                'label' => 'Ranking snapshots and seed settings',
                'tables' => [
                    'tournament_seed_players',
                    'pro_bowler_seed_list_players',
                    'pro_bowler_seed_lists',
                    'pro_bowler_ranking_rows',
                    'pro_bowler_ranking_snapshots',
                ],
            ],
            'tournament_results_and_settings' => [
                'label' => 'Tournament scores, results, files, points, prizes, and tournament rows',
                'tables' => [
                    'pro_bowler_titles',
                    'game_scores',
                    'tournamentscore',
                    'stage_settings',
                    'match_videos',
                    'media_publications',
                    'tournament_files',
                    'tournament_awards',
                    'tournament_points',
                    'tournament_results',
                    'tournament_participants',
                    'tournament_organizations',
                    'point_distributions',
                    'prize_distributions',
                    'calendar_events',
                    'calendar_days',
                    'tournaments',
                    'amateur_bowlers',
                ],
            ],
            'pro_bowler_and_instructor_data' => [
                'label' => 'Pro bowler, instructor, ball, training, title, and record data',
                'tables' => [
                    'approved_ball_pro_bowler',
                    'record_types',
                    'hof_photos',
                    'hof_inductions',
                    'registered_balls',
                    'used_balls',
                    'pro_bowler_trainings',
                    'pro_bowler_instructor_info',
                    'pro_bowler_sponsors',
                    'pro_bowler_biographies',
                    'pro_bowler_links',
                    'pro_bowler_profiles',
                    'instructor_registry',
                    'instructors',
                    'pro_dsp',
                    'pro_bowlers',
                ],
            ],
        ];

        if ($includeContent) {
            $groups['public_content'] = [
                'label' => 'Public news/information content',
                'tables' => [
                    'information_files',
                    'informations',
                    'flash_news',
                ],
            ];
        }

        if ($includeProTest) {
            $groups['pro_test_operational_data'] = [
                'label' => 'Pro-test schedules, applications, scores, comments, and attachments',
                'tables' => [
                    'pro_test_status_log',
                    'pro_test_score_summary',
                    'pro_test_attachment',
                    'pro_test_comment',
                    'pro_test_score',
                    'pro_test',
                    'pro_test_schedule',
                ],
            ];
        }

        return $groups;
    }

    private function createOrUpdateAdmin(string $adminEmail): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $attributes = [
            'name' => (string) $this->option('admin-name'),
            'email' => $adminEmail,
            'password' => (string) $this->option('admin-password'),
        ];

        if (Schema::hasColumn('users', 'role')) {
            $attributes['role'] = 'admin';
        }

        if (Schema::hasColumn('users', 'is_admin')) {
            $attributes['is_admin'] = true;
        }

        if (Schema::hasColumn('users', 'pro_bowler_id')) {
            $attributes['pro_bowler_id'] = null;
        }

        if (Schema::hasColumn('users', 'pro_bowler_license_no')) {
            $attributes['pro_bowler_license_no'] = null;
        }

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $attributes['email_verified_at'] = now();
        }

        User::query()->updateOrCreate(['email' => $adminEmail], $attributes);
    }

    private function deleteNonAdminUsers(string $adminEmail): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->where(function ($query) use ($adminEmail): void {
                $query->where('email', '<>', $adminEmail)
                    ->orWhereNull('email');
            })
            ->delete();

        $updates = [];
        if (Schema::hasColumn('users', 'role')) {
            $updates['role'] = 'admin';
        }
        if (Schema::hasColumn('users', 'is_admin')) {
            $updates['is_admin'] = true;
        }
        if (Schema::hasColumn('users', 'pro_bowler_id')) {
            $updates['pro_bowler_id'] = null;
        }
        if (Schema::hasColumn('users', 'pro_bowler_license_no')) {
            $updates['pro_bowler_license_no'] = null;
        }

        if ($updates !== []) {
            DB::table('users')->where('email', $adminEmail)->update($updates);
        }
    }

    private function deleteTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->delete();
    }

    private function renderReport(array $report): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return;
        }

        $this->info($report['message'] ?? 'Forward-test reset report');
        $this->line('Generated at: '.$report['generated_at']);
        $this->line('Target rows: '.$report['target_row_count']);
        $this->line('Admin email: '.($report['admin']['admin_email'] ?? '(not set)'));
        $this->line('Users total: '.$report['admin']['users_total']);
        $this->line('Users to delete on force: '.$report['admin']['users_to_delete']);

        $this->newLine();
        $this->line('Current admin candidates:');
        foreach ($report['admin']['admin_candidates'] as $admin) {
            $this->line('  - '.json_encode($admin, JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();
        foreach ($report['groups'] as $key => $group) {
            $this->line(sprintf('[%s] %s rows - %s', $key, $group['row_count'], $group['label']));
            foreach ($group['tables'] as $table => $count) {
                $this->line(sprintf('  %s: %s', $table, $count === null ? 'missing' : (string) $count));
            }
        }

        if ($report['missing_tables'] !== []) {
            $this->newLine();
            $this->warn('Missing tables: '.implode(', ', $report['missing_tables']));
        }
    }
}
