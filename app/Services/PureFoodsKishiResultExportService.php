<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\TournamentResultFormatVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PureFoodsKishiResultExportService
{
    public function __construct(
        private readonly TournamentResultExcelPdfConverter $pdfConverter,
    ) {
    }

    public function downloadWorkbook(array $data, string $downloadName): BinaryFileResponse
    {
        $path = $this->buildWorkbook($data);

        return response()
            ->download($path, $this->safeName($downloadName, 'xlsx'))
            ->deleteFileAfterSend(true);
    }

    public function downloadPdf(array $data, string $downloadName): BinaryFileResponse
    {
        $xlsxPath = $this->buildWorkbook($data);
        $pdfPath = preg_replace('/\.xlsx$/i', '.pdf', $xlsxPath) ?: $xlsxPath.'.pdf';

        try {
            $this->pdfConverter->convert($xlsxPath, $pdfPath);
        } finally {
            @unlink($xlsxPath);
        }

        return response()
            ->download($pdfPath, $this->safeName($downloadName, 'pdf'))
            ->deleteFileAfterSend(true);
    }

    public function buildWorkbook(array $data): string
    {
        $tournament = $data['tournament'];
        $tournament->loadMissing([
            'organizations',
            'resultFormatVersion.format',
        ]);

        $version = $tournament->resultFormatVersion;
        if (! $version instanceof TournamentResultFormatVersion || $version->format?->code !== 'purefoods_kishi') {
            throw new RuntimeException('ピュアフーズ岸方式のExcel版が大会に設定されていません。');
        }

        $workDirectory = storage_path('app/private/tournament_result_exports');
        if (! is_dir($workDirectory) && ! mkdir($workDirectory, 0775, true) && ! is_dir($workDirectory)) {
            throw new RuntimeException('Excel一時保存先を作成できません。');
        }

        $token = 'purefoods_'.(int) $tournament->id.'_'.bin2hex(random_bytes(8));
        $outputPath = $workDirectory.DIRECTORY_SEPARATOR.$token.'.xlsx';
        $assetDirectory = $workDirectory.DIRECTORY_SEPARATOR.$token.'_assets';
        if (! mkdir($assetDirectory, 0775, true) && ! is_dir($assetDirectory)) {
            throw new RuntimeException('画像一時保存先を作成できません。');
        }

        $book = IOFactory::load($version->absoluteTemplatePath());

        try {
            $this->populateOverview($book, $data);
            $this->populateFinalResults($book, $data, $assetDirectory);
            $this->populateFinalScorePage($book, $data, $assetDirectory);
            $this->populateBracketPage($book, $data, $assetDirectory);
            $annualSettingsSheet = $book->getSheetByName('年度設定');
            if ($annualSettingsSheet !== null) {
                $annualSettingsSheet->setSheetState(
                    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN
                );
            }
            $overviewSheet = $book->getSheetByName('1_大会概要');
            $book->setActiveSheetIndex($overviewSheet !== null ? $book->getIndex($overviewSheet) : 0);
            IOFactory::createWriter($book, 'Xlsx')->save($outputPath);
        } finally {
            $book->disconnectWorksheets();
            foreach (glob($assetDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $asset) {
                @unlink($asset);
            }
            @rmdir($assetDirectory);
        }

        return $outputPath;
    }

    private function populateOverview(Spreadsheet $book, array $data): void
    {
        $tournament = $data['tournament'];
        $settings = $this->formatSettings($tournament);
        $organizations = $tournament->organizations
            ->groupBy('category')
            ->map(fn (Collection $rows): string => $rows->sortBy('sort_order')->pluck('name')->filter()->implode(' / '));

        $prelim = collect($data['scoreSnapshots'] ?? [])->first(
            fn (array $snapshot): bool => (string) ($snapshot['snapshot']->result_code ?? '') === 'prelim_total'
        );
        $participantCount = $prelim ? count($prelim['rows'] ?? []) : collect($data['results'] ?? [])->count();

        $dates = collect([$tournament->start_date, $tournament->end_date])
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->format('Y年n月j日'))
            ->unique()
            ->implode('～');

        $schedule = trim((string) ($settings['schedule_text'] ?? ''));
        if ($schedule === '') {
            $schedule = collect($tournament->sidebar_schedule ?? [])
                ->map(function (array $row): string {
                    return trim(implode(' ', array_filter([
                        trim((string) ($row['date'] ?? '')),
                        trim((string) ($row['label'] ?? '')),
                    ])));
                })
                ->filter()
                ->implode("\n");
        }

        $broadcast = trim((string) ($settings['broadcast_text'] ?? ''));
        if ($broadcast === '') {
            $broadcast = collect([$tournament->broadcast, $tournament->streaming])
                ->filter(fn ($value): bool => trim((string) $value) !== '')
                ->implode(' / ');
        }

        $hostDisplay = (string) ($organizations->get('host') ?: $tournament->host ?: '');
        if (str_contains($hostDisplay, 'KISHIKAGAKU GROUP') && str_contains($hostDisplay, 'ピュアフーズ岸')) {
            $hostDisplay = '　　　 KISHIKAGAKU GROUP・　　　 ピュアフーズ岸株式会社';
        }

        $overviewTitle = $this->displayTitle($tournament);
        if (str_contains($overviewTitle, 'プレゼンツ')) {
            $overviewTitle = preg_replace('/プレゼンツ\s*/u', "プレゼンツ\n", $overviewTitle, 1) ?: $overviewTitle;
        }

        $sponsorDisplay = collect([
            $organizations->get('special_sponsor') ?: $tournament->special_sponsor,
            $organizations->get('sponsor') ?: $tournament->sponsor,
        ])->filter()->implode(' / ');
        $venueContact = collect([
            $tournament->venue_address,
            $tournament->venue_tel ? 'TEL '.$tournament->venue_tel : null,
            $tournament->venue_fax ? 'FAX '.$tournament->venue_fax : null,
        ])->filter()->implode(' / ');

        $this->setNamed($book, 'PF_TITLE', $this->templateOverride($book, 'PF_INPUT_OVERVIEW_TITLE', $overviewTitle));
        $this->setNamed($book, 'PF_ENGLISH_TITLE', $this->templateOverride(
            $book,
            'PF_INPUT_ENGLISH_TITLE',
            $settings['english_title'] ?? $tournament->name
        ));
        $this->setNamed($book, 'PF_TAGLINE', $this->templateOverride($book, 'PF_INPUT_TAGLINE', $settings['tagline'] ?? ''));
        $this->setNamed($book, 'PF_HOST', $this->templateOverride($book, 'PF_INPUT_HOST', $hostDisplay));
        $this->setNamed($book, 'PF_CO_HOST', $this->templateOverride($book, 'PF_INPUT_CO_HOST', $organizations->get('co_host') ?: ''));
        $this->setNamed($book, 'PF_SUPPORT', $this->templateOverride($book, 'PF_INPUT_SUPPORT', $organizations->get('support') ?: $tournament->support));
        $this->setNamed($book, 'PF_SPONSOR', $this->templateOverride($book, 'PF_INPUT_SPONSOR', $sponsorDisplay));
        $this->setNamed($book, 'PF_COOPERATION', $this->templateOverride($book, 'PF_INPUT_COOPERATION', $organizations->get('cooperation') ?: ''));
        $this->setNamed($book, 'PF_SUPERVISOR', $this->templateOverride($book, 'PF_INPUT_SUPERVISOR', $organizations->get('supervisor') ?: $tournament->supervisor));
        $this->setNamed($book, 'PF_AUTHORIZED', $this->templateOverride($book, 'PF_INPUT_AUTHORIZED', $organizations->get('authorized') ?: $tournament->authorized_by));
        $this->setNamed($book, 'PF_DATES', $this->templateOverride($book, 'PF_INPUT_DATES', $dates));
        $this->setNamed($book, 'PF_VENUE', $this->templateOverride($book, 'PF_INPUT_VENUE', $tournament->venue_name));
        $this->setNamed($book, 'PF_VENUE_CONTACT', $this->templateOverride($book, 'PF_INPUT_VENUE_CONTACT', $venueContact));
        $this->setNamed($book, 'PF_PARTICIPANTS', $participantCount > 0 ? $participantCount.'名' : '');
        $this->setNamed($book, 'PF_SCHEDULE', $this->templateOverride($book, 'PF_INPUT_SCHEDULE', $schedule));
        $this->setNamed($book, 'PF_BROADCAST', $this->templateOverride($book, 'PF_INPUT_BROADCAST', $broadcast));
    }

    private function populateFinalResults(Spreadsheet $book, array $data, string $assetDirectory): void
    {
        $tournament = $data['tournament'];
        $settings = $this->formatSettings($tournament);
        $results = collect($data['results'] ?? [])->sortBy('ranking')->values();
        $sheet = $book->getSheetByName('2_最終成績');
        if ($sheet === null) {
            throw new RuntimeException('Excelに「2_最終成績」シートがありません。');
        }

        $this->setNamed(
            $book,
            'PF2_TITLE',
            $this->templateOverride($book, 'PF_INPUT_RESULT_TITLE', $this->displayTitle($tournament))
        );
        $prizeTotal = array_sum(array_map('intval', $data['prizeDistributionMap'] ?? []));
        $this->setNamed($book, 'PF2_PRIZE_TOTAL', $prizeTotal > 0 ? '賞金総額 '.number_format($prizeTotal).'円' : ($tournament->prize ?: ''));

        $stageScores = $this->buildStageScoreMap($data);
        foreach (range(7, 42) as $rowNo) {
            foreach (['A', 'D', 'G', 'K', 'M', 'S', 'T', 'U', 'V', 'W', 'Y'] as $column) {
                $sheet->setCellValue($column.$rowNo, null);
            }
        }

        foreach ($results->take(36) as $index => $result) {
            $rowNo = 7 + $index;
            $profile = $result->player ?: $result->bowler;
            $license = (string) ($result->pro_bowler_license_no ?? $profile?->license_no ?? '');
            $name = trim((string) ($profile?->name_kanji ?? $result->amateur_name ?? ''));
            $key = $this->participantKey($license, $name);
            $scores = $stageScores[$key] ?? [];
            $rank = (int) ($result->ranking ?? ($index + 1));

            $sheet->setCellValue('A'.$rowNo, $rank === 1 ? '優勝' : '第'.$rank.'位');
            $sheet->setCellValueExplicit(
                'D'.$rowNo,
                $this->licenseDigits($license),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValue('G'.$rowNo, $name);
            $sheet->setCellValue('K'.$rowNo, $result->period_label ?? ($profile?->kibetsu ? $profile->kibetsu.'期' : ''));
            $sheet->setCellValue('M'.$rowNo, $result->pdf_affiliation_display ?? '-');
            if ($rank >= 13) {
                $sheet->setCellValue('S'.$rowNo, $rank <= 24 ? '18G' : '12G');
                $sheet->setCellValue('T'.$rowNo, $result->total_pin ?? null);
            } else {
                $sheet->setCellValue('S'.$rowNo, $scores['round1'] ?? null);
                $sheet->setCellValue('T'.$rowNo, $scores['round2'] ?? null);
                $sheet->setCellValue('U'.$rowNo, $scores['semifinal'] ?? null);
                $sheet->setCellValue('V'.$rowNo, $scores['final'] ?? null);
            }
            $sheet->setCellValue('W'.$rowNo, (int) ($result->points ?? 0));
            $winnerPrizeDisplay = trim((string) ($settings['winner_prize_display'] ?? ''));
            if ($rank === 1 && $winnerPrizeDisplay !== '') {
                $sheet->setCellValue('Y'.$rowNo, $winnerPrizeDisplay);
                $winnerPrizeStyle = $sheet->getStyle('Y'.$rowNo.':AD'.$rowNo);
                $winnerPrizeStyle->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $winnerPrizeStyle->getFont()->setSize(8);
            } else {
                $sheet->setCellValue('Y'.$rowNo, (int) (($data['prizeDistributionMap'][$rank] ?? null) ?? $result->prize_money ?? 0));
                $sheet->getStyle('Y'.$rowNo.':AD'.$rowNo)->getNumberFormat()->setFormatCode('#,##0');
            }
            $sheet->getStyle('D'.$rowNo.':F'.$rowNo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $winnerResult = $results->first(fn ($result): bool => (int) ($result->ranking ?? 0) === 1) ?: $results->first();
        $winner = $winnerResult?->player ?: $winnerResult?->bowler;
        if ($winner instanceof ProBowler) {
            $winner->loadMissing(['district', 'officialTitles']);
            $birthday = $winner->birthdate_public_is_private
                ? null
                : ($winner->birthdate_public ?: $winner->birthdate);
            $age = $birthday
                ? (int) floor(Carbon::parse($birthday)->diffInYears(Carbon::parse($tournament->end_date ?: $tournament->start_date ?: now())))
                : null;

            $this->setNamed($book, 'PF2_WINNER_ROMAJI', $settings['winner_roman_name'] ?? $winner->name_kana);
            $this->setNamed($book, 'PF2_WINNER_PROFILE', trim(implode('　', array_filter([
                $winner->name_kanji,
                $age !== null ? $age.'歳' : null,
                $winner->kibetsu ? '（'.$winner->kibetsu.'期）' : null,
                $winner->pro_entry_year ? 'プロ入り'.$winner->pro_entry_year.'年' : null,
            ]))));
            $this->setNamed($book, 'PF2_WINNER_BIO', trim(implode('　', array_filter([
                $birthday ? '生年月日：'.Carbon::parse($birthday)->format('Y年n月j日') : null,
                $winner->birthplace ? '出身地：'.$winner->birthplace : null,
                $winner->dominant_arm ? $winner->dominant_arm.'投げ' : null,
            ]))));
            $this->setNamed(
                $book,
                'PF2_WINNER_HEADLINE',
                $settings['winner_headline'] ?? ('優勝 '.$winner->name_kanji)
            );
            $this->setNamed(
                $book,
                'PF2_WINNER_RECORD',
                $settings['winner_record'] ?? trim(implode("\n", array_filter([
                    $winner->official_win_count ? '通算'.$winner->official_win_count.'勝' : null,
                    collect($tournament->result_cards ?? [])->first()['balls'] ?? null,
                ])))
            );

            $photo = $this->resolveProfilePhotoPath($winner, $assetDirectory);
            if ($photo !== null) {
                $this->setNamed($book, 'PF2_WINNER_PHOTO_ANCHOR', '');
                $this->addDrawingAtNamedRange($book, 'PF2_WINNER_PHOTO_ANCHOR', $photo, 170, 220);
            }
        }

        $this->setNamed(
            $book,
            'PF2_AWARDS',
            $settings['awards_text'] ?? $this->buildAwardsText($tournament)
        );
        $this->setNamed(
            $book,
            'PF2_PREVIOUS_RESULTS',
            $settings['previous_results_text'] ?? ''
        );
    }

    private function populateFinalScorePage(Spreadsheet $book, array $data, string $assetDirectory): void
    {
        $tournament = $data['tournament'];
        $results = collect($data['results'] ?? [])->sortBy('ranking')->values();
        $resultForRank = fn (int $rank) => $results->first(
            fn ($row): bool => (int) ($row->ranking ?? 0) === $rank
        );
        $nameForRank = function (int $rank) use ($resultForRank): string {
            $result = $resultForRank($rank);

            return trim((string) ($result?->player?->name_kanji ?? $result?->bowler?->name_kanji ?? $result?->amateur_name ?? ''));
        };
        $profileForRank = function (int $rank) use ($resultForRank, $nameForRank): string {
            $result = $resultForRank($rank);
            $profile = $result?->player ?: $result?->bowler;
            $license = $this->licenseDigits((string) ($result?->pro_bowler_license_no ?? $profile?->license_no ?? ''));
            $license = ltrim($license, '0') ?: $license;
            $term = $result?->period_label ?? ($profile?->kibetsu ? $profile->kibetsu.'期生' : '');

            return trim(implode("\n", array_filter([
                '第'.$rank.'位',
                $license !== '' ? 'No.'.$license.'（'.$term.'）' : $term,
                $nameForRank($rank),
            ])));
        };

        $this->setNamed(
            $book,
            'PF3_TITLE',
            $this->templateOverride($book, 'PF_INPUT_RESULT_TITLE', $this->displayTitle($tournament))
        );
        $this->setNamed($book, 'PF3_FINALIST_2', $profileForRank(2));
        $this->setNamed($book, 'PF3_FINALIST_3', $profileForRank(3));
        $this->setNamed($book, 'PF3_FINALIST_4', $profileForRank(4));
        $this->setNamed($book, 'PF3_CHAMPION', str_replace('第1位', '優勝', $profileForRank(1)));

        $semifinalLines = collect($data['singleEliminationMatchSummary'] ?? [])
            ->filter(fn (array $match): bool => str_contains((string) ($match['label'] ?? ''), '準決勝'))
            ->map(function (array $match): string {
                return collect($match['players'] ?? [])
                    ->map(fn (array $player): string => trim((string) ($player['name'] ?? '')).' '.number_format((int) ($player['total_pin'] ?? 0)))
                    ->implode(' / ');
            })
            ->filter()
            ->implode("\n");
        if ($semifinalLines === '') {
            $semifinalLines = collect($data['matchScoreSheets'] ?? [])
                ->filter(fn ($sheet): bool => str_contains(
                    (string) ($sheet->match_label ?: $sheet->match_code ?: ''),
                    '準決勝'
                ))
                ->map(function ($sheet): string {
                    return collect($sheet->players ?? [])
                        ->map(fn ($player): string => trim((string) $player->display_name).' '.number_format((int) $player->final_score))
                        ->implode(' / ');
                })
                ->filter()
                ->implode("\n");
        }
        $this->setNamed($book, 'PF3_SEMIFINAL_SCORES', $semifinalLines);
        $this->setNamed($book, 'PF3_WINNER_BOX', trim($nameForRank(1)."\n".($this->formatSettings($tournament)['winner_headline'] ?? '')));

        $images = collect($data['matchScoreSheetImages'] ?? []);
        $final = $images->first(fn (array $image): bool => str_contains((string) ($image['match_label'] ?? ''), '優勝')
            || (str_contains((string) ($image['match_label'] ?? ''), '決勝')
                && ! str_contains((string) ($image['match_label'] ?? ''), '準決勝')));
        $semifinals = $images
            ->filter(fn (array $image): bool => str_contains((string) ($image['match_label'] ?? ''), '準決勝'))
            ->values();

        $imageAssignments = [
            'PF3_FINAL_IMAGE_ANCHOR' => $final ?: $images->get(2),
            'PF3_SF2_IMAGE_ANCHOR' => $semifinals->get(1) ?: $images->get(1),
            'PF3_SF1_IMAGE_ANCHOR' => $semifinals->get(0) ?: $images->get(0),
        ];

        foreach ($imageAssignments as $namedRange => $image) {
            if (! is_array($image) || empty($image['image'])) {
                continue;
            }
            $path = $this->writeDataUriImage((string) $image['image'], $assetDirectory);
            $this->addDrawingAtNamedRange($book, $namedRange, $path, 670, 155);
        }
    }

    private function populateBracketPage(Spreadsheet $book, array $data, string $assetDirectory): void
    {
        $tournament = $data['tournament'];
        $settings = $this->formatSettings($tournament);
        $this->setNamed(
            $book,
            'PF4_TITLE',
            $this->templateOverride($book, 'PF_INPUT_RESULT_TITLE', $this->displayTitle($tournament))
        );
        $bracketDateVenue = trim(implode('　', array_filter([
            ($tournament->end_date ?: $tournament->start_date)
                ? Carbon::parse($tournament->end_date ?: $tournament->start_date)->format('Y年n月j日')
                : null,
            $tournament->venue_name,
        ])));
        $this->setNamed(
            $book,
            'PF4_DATE_VENUE',
            $this->templateOverride($book, 'PF_INPUT_BRACKET_DATE_VENUE', $bracketDateVenue)
        );
        $this->setNamed(
            $book,
            'PF4_RULES',
            $this->templateOverride(
                $book,
                'PF_INPUT_BRACKET_RULES',
                $settings['bracket_rules'] ?? $this->buildBracketRules($data['singleEliminationMatchSummary'] ?? [])
            )
        );
        $this->setNamed(
            $book,
            'PF4_FOOTNOTE',
            $this->templateOverride(
                $book,
                'PF_INPUT_BRACKET_FOOTNOTE',
                $settings['footnote'] ?? '同スコアの場合は大会規程により勝者を決定'
            )
        );

        $bracketImage = $this->completedBracketImage($data) ?: ($data['singleEliminationBracketImage'] ?? null);
        if (! empty($bracketImage)) {
            $path = $this->writeDataUriImage(
                (string) $bracketImage,
                $assetDirectory,
                cropWhiteMargins: true
            );
            $this->addDrawingAtNamedRange($book, 'PF4_BRACKET_IMAGE_ANCHOR', $path, 920, 500);
        }
    }

    private function completedBracketImage(array $data): ?string
    {
        $payload = $data['singleEliminationPdf'] ?? null;
        if (! is_array($payload) || empty($payload['bracket']['rounds'])) {
            return null;
        }

        $rounds = array_values((array) $payload['bracket']['rounds']);
        if (count($rounds) < 2) {
            return null;
        }

        $semifinalRound = (array) $rounds[count($rounds) - 2];
        $finalRound = (array) $rounds[count($rounds) - 1];
        $semifinalCodes = collect($semifinalRound['matches'] ?? [])
            ->map(fn ($match): string => (string) ($match['match_key'] ?? $match['code'] ?? ''))
            ->filter()
            ->values();
        $finalMatch = (array) (collect($finalRound['matches'] ?? [])->first() ?? []);
        $finalCode = (string) ($finalMatch['match_key'] ?? $finalMatch['code'] ?? '');
        if ($semifinalCodes->isEmpty() || $finalCode === '') {
            return null;
        }

        $scoreSheets = collect($data['matchScoreSheets'] ?? []);
        $semifinalSheets = $scoreSheets
            ->filter(fn ($sheet): bool => str_contains(
                strtoupper((string) ($sheet->match_code ?: $sheet->match_label ?: '')),
                'SF'
            ) || str_contains((string) ($sheet->match_label ?? ''), '準決勝'))
            ->sortBy(fn ($sheet) => [(int) ($sheet->match_order ?? 0), (int) $sheet->id])
            ->values();
        $finalSheet = $scoreSheets->first(fn ($sheet): bool => str_contains(
            strtoupper((string) ($sheet->match_code ?: $sheet->match_label ?: '')),
            'FINAL'
        ) || (str_contains((string) ($sheet->match_label ?? ''), '決勝')
            && ! str_contains((string) ($sheet->match_label ?? ''), '準決勝')));

        $matchScores = [];
        foreach ($semifinalCodes as $index => $code) {
            $sheet = $semifinalSheets->get($index);
            if ($sheet) {
                $matchScores[$code] = $this->scoreSheetMatchScores($sheet);
            }
        }
        if ($finalSheet) {
            $matchScores[$finalCode] = $this->scoreSheetMatchScores($finalSheet);
        }
        if ($matchScores === []) {
            return null;
        }

        try {
            /** @var SingleEliminationService $singleEliminationService */
            $singleEliminationService = app(SingleEliminationService::class);
            $payload['bracket'] = $singleEliminationService->applyMatchScores(
                (array) $payload['bracket'],
                $matchScores
            );

            /** @var SingleEliminationBracketImageService $imageService */
            $imageService = app(SingleEliminationBracketImageService::class);

            return $imageService->generateDataUri($data['tournament'], $payload);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function scoreSheetMatchScores($scoreSheet): array
    {
        $scores = [];
        foreach (collect($scoreSheet->players ?? [])->values() as $index => $player) {
            $slot = strtoupper(trim((string) ($player->player_slot ?? '')));
            if (! in_array($slot, ['A', 'B'], true)) {
                $slot = $index === 0 ? 'A' : 'B';
            }
            $scores[$slot] = [
                'score' => (int) ($player->final_score ?? 0),
                'license_number' => $player->pro_bowler_license_no ?? null,
                'name' => $player->display_name ?? null,
                'pro_bowler_id' => $player->pro_bowler_id ?? null,
            ];
        }

        return $scores;
    }

    /**
     * @return array<string,array{round1?:int,round2?:int,semifinal?:int,final?:int}>
     */
    private function buildStageScoreMap(array $data): array
    {
        $map = [];
        foreach ($data['singleEliminationMatchSummary'] ?? [] as $match) {
            $code = strtoupper((string) ($match['code'] ?? ''));
            $stage = str_contains($code, 'R1-') ? 'round1' : (str_contains($code, 'R2-') ? 'round2' : null);
            if ($stage === null) {
                continue;
            }

            foreach ($match['players'] ?? [] as $player) {
                $key = $this->participantKey((string) ($player['license'] ?? ''), (string) ($player['name'] ?? ''));
                $map[$key][$stage] = (int) ($player['total_pin'] ?? 0);
            }
        }

        foreach ($data['matchScoreSheets'] ?? [] as $scoreSheet) {
            $label = strtoupper(implode(' ', array_filter([
                $scoreSheet->stage_code ?? null,
                $scoreSheet->match_code ?? null,
                $scoreSheet->match_label ?? null,
            ])));
            $stage = str_contains($label, 'SF') || str_contains($label, '準決勝')
                ? 'semifinal'
                : ((str_contains($label, 'FINAL') || str_contains($label, '優勝')) ? 'final' : null);
            if ($stage === null) {
                continue;
            }

            foreach ($scoreSheet->players ?? [] as $player) {
                $key = $this->participantKey(
                    (string) ($player->pro_bowler_license_no ?? ''),
                    (string) ($player->display_name ?? '')
                );
                $map[$key][$stage] = (int) ($player->final_score ?? 0);
            }
        }

        return $map;
    }

    private function setNamed(Spreadsheet $book, string $name, mixed $value): void
    {
        $defined = $book->getDefinedName($name);
        if ($defined === null || $defined->getWorksheet() === null) {
            throw new RuntimeException('Excelの名前付き範囲がありません: '.$name);
        }

        $address = str_replace('$', '', $defined->getValue());
        if (str_contains($address, '!')) {
            $address = substr($address, strrpos($address, '!') + 1);
        }
        $coordinate = explode(':', trim($address, "'"))[0];
        $defined->getWorksheet()->setCellValue($coordinate, $value ?? '');
    }

    private function templateOverride(Spreadsheet $book, string $name, mixed $fallback): mixed
    {
        $defined = $book->getDefinedName($name);
        if ($defined === null || $defined->getWorksheet() === null) {
            return $fallback;
        }

        $address = str_replace('$', '', $defined->getValue());
        if (str_contains($address, '!')) {
            $address = substr($address, strrpos($address, '!') + 1);
        }
        $coordinate = explode(':', trim($address, "'"))[0];
        $value = $defined->getWorksheet()->getCell($coordinate)->getValue();

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === null || $value === '' ? $fallback : $value;
    }

    private function addDrawingAtNamedRange(
        Spreadsheet $book,
        string $namedRange,
        string $imagePath,
        int $width,
        int $height
    ): void {
        $defined = $book->getDefinedName($namedRange);
        if ($defined === null || $defined->getWorksheet() === null) {
            throw new RuntimeException('画像差し込み用の名前付き範囲がありません: '.$namedRange);
        }

        $address = str_replace('$', '', $defined->getValue());
        if (str_contains($address, '!')) {
            $address = substr($address, strrpos($address, '!') + 1);
        }
        $coordinate = explode(':', trim($address, "'"))[0];

        $drawing = new Drawing();
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinate);
        $drawing->setOffsetX(2);
        $drawing->setOffsetY(2);
        $drawing->setWidth($width);
        if ($drawing->getHeight() > $height) {
            $drawing->setHeight($height);
        }
        $drawing->setWorksheet($defined->getWorksheet());
    }

    private function writeDataUriImage(
        string $dataUri,
        string $assetDirectory,
        bool $cropWhiteMargins = false
    ): string
    {
        if (! preg_match('#^data:image/([a-zA-Z0-9.+-]+);base64,(.+)$#s', $dataUri, $match)) {
            throw new RuntimeException('PDF用の画像データ形式が不正です。');
        }

        $binary = base64_decode($match[2], true);
        if ($binary === false) {
            throw new RuntimeException('PDF用画像を復号できません。');
        }

        $extension = str_contains(strtolower($match[1]), 'jpeg') ? 'jpg' : 'png';
        if ($cropWhiteMargins) {
            $image = @imagecreatefromstring($binary);
            if ($image !== false) {
                $width = imagesx($image);
                $height = imagesy($image);
                $minX = $width;
                $minY = $height;
                $maxX = 0;
                $maxY = 0;

                for ($y = 0; $y < $height; $y += 2) {
                    for ($x = 0; $x < $width; $x += 2) {
                        $rgb = imagecolorat($image, $x, $y);
                        $red = ($rgb >> 16) & 0xff;
                        $green = ($rgb >> 8) & 0xff;
                        $blue = $rgb & 0xff;
                        if ($red < 245 || $green < 245 || $blue < 245) {
                            $minX = min($minX, $x);
                            $minY = min($minY, $y);
                            $maxX = max($maxX, $x);
                            $maxY = max($maxY, $y);
                        }
                    }
                }

                if ($maxX > $minX && $maxY > $minY) {
                    $margin = 8;
                    $crop = imagecrop($image, [
                        'x' => max(0, $minX - $margin),
                        'y' => max(0, $minY - $margin),
                        'width' => min($width, $maxX + $margin) - max(0, $minX - $margin),
                        'height' => min($height, $maxY + $margin) - max(0, $minY - $margin),
                    ]);
                    if ($crop !== false) {
                        ob_start();
                        imagepng($crop);
                        $croppedBinary = ob_get_clean();
                        imagedestroy($crop);
                        if (is_string($croppedBinary) && $croppedBinary !== '') {
                            $binary = $croppedBinary;
                            $extension = 'png';
                        }
                    }
                }
                imagedestroy($image);
            }
        }
        $path = $assetDirectory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8)).'.'.$extension;
        file_put_contents($path, $binary);

        return $path;
    }

    private function resolveProfilePhotoPath(ProBowler $winner, string $assetDirectory): ?string
    {
        foreach ([$winner->public_image_path, $winner->image_path] as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            foreach ([
                $path,
                public_path(ltrim($path, '/\\')),
                public_path('storage/'.ltrim($path, '/\\')),
                storage_path('app/public/'.ltrim($path, '/\\')),
            ] as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            $url = preg_match('~^https?://~i', $path)
                ? $path
                : null;
            if ($url === null && str_starts_with($path, '/')) {
                $profileUrl = trim((string) $winner->official_profile_url);
                $scheme = parse_url($profileUrl, PHP_URL_SCHEME) ?: 'https';
                $host = parse_url($profileUrl, PHP_URL_HOST) ?: 'www.jpba1.jp';
                $url = $scheme.'://'.$host.$path;
            }

            if ($url !== null) {
                try {
                    $request = Http::timeout(15)->retry(1, 250);
                    if (app()->environment('local')) {
                        $request = $request->withoutVerifying();
                    }
                    $response = $request->get($url);
                    if ($response->successful() && str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/')) {
                        $extension = str_contains(strtolower((string) $response->header('Content-Type')), 'png') ? 'png' : 'jpg';
                        $downloadPath = $assetDirectory.DIRECTORY_SEPARATOR.'winner_profile.'.$extension;
                        file_put_contents($downloadPath, $response->body());

                        return $downloadPath;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return null;
    }

    private function buildAwardsText($tournament): string
    {
        return collect($tournament->award_highlights ?? [])
            ->map(function (array $award): string {
                return trim(implode(' ', array_filter([
                    $award['title'] ?? $award['type'] ?? null,
                    $award['player'] ?? null,
                    $award['note'] ?? null,
                ])));
            })
            ->filter()
            ->implode("\n");
    }

    private function buildBracketRules(array $matches): string
    {
        $rounds = [];
        foreach ($matches as $match) {
            $code = strtoupper((string) ($match['code'] ?? ''));
            if (! preg_match('/R(\d+)-/', $code, $found)) {
                continue;
            }
            $games = collect($match['players'] ?? [])->max(fn (array $player): int => count($player['scores'] ?? []));
            $rounds[(int) $found[1]] = max((int) ($rounds[(int) $found[1]] ?? 0), (int) $games);
        }

        return collect($rounds)
            ->sortKeys()
            ->map(fn (int $games, int $round): string => '第'.$round.'回戦 '.$games.'Gトータルピン')
            ->implode(' / ');
    }

    private function participantKey(string $license, string $name): string
    {
        $digits = $this->licenseDigits($license);
        if ($digits !== '') {
            return 'license:'.(ltrim($digits, '0') ?: '0');
        }

        return 'name:'.preg_replace('/\s+/u', '', trim($name));
    }

    private function licenseDigits(string $license): string
    {
        return preg_replace('/\D+/', '', strtoupper(trim($license))) ?: '';
    }

    private function formatSettings($tournament): array
    {
        $snapshot = is_array($tournament->template_snapshot) ? $tournament->template_snapshot : [];
        $settings = $snapshot['result_format'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function displayTitle($tournament): string
    {
        $name = trim((string) $tournament->name);
        $year = trim((string) $tournament->year);

        return $year !== '' && ! str_contains($name, $year)
            ? $name.' '.$year
            : $name;
    }

    private function safeName(string $name, string $extension): string
    {
        $name = preg_replace('/[\\\\\\/:*?"<>|]+/u', '_', $name) ?: 'tournament_results';
        $name = preg_replace('/\.(pdf|xlsx)$/i', '', $name) ?: 'tournament_results';

        return $name.'.'.$extension;
    }
}
