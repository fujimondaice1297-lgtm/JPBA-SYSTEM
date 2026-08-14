<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\TournamentResultFormatVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RokkoQueensResultExportService
{
    public function __construct(
        private readonly TournamentResultExcelPdfConverter $pdfConverter,
        private readonly StepLadderBracketImageService $stepLadderBracketImageService,
        private readonly MatchScoreSheetImageService $matchScoreSheetImageService,
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
        if (! $version instanceof TournamentResultFormatVersion || $version->format?->code !== 'rokko_queens') {
            throw new RuntimeException('六甲クイーンズ方式のExcel版が大会に設定されていません。');
        }

        $workDirectory = storage_path('app/private/tournament_result_exports');
        if (! is_dir($workDirectory) && ! mkdir($workDirectory, 0775, true) && ! is_dir($workDirectory)) {
            throw new RuntimeException('Excel一時保存先を作成できません。');
        }

        $token = 'rokko_'.(int) $tournament->id.'_'.bin2hex(random_bytes(8));
        $outputPath = $workDirectory.DIRECTORY_SEPARATOR.$token.'.xlsx';
        $assetDirectory = $workDirectory.DIRECTORY_SEPARATOR.$token.'_assets';
        if (! mkdir($assetDirectory, 0775, true) && ! is_dir($assetDirectory)) {
            throw new RuntimeException('画像一時保存先を作成できません。');
        }

        $book = IOFactory::load($version->absoluteTemplatePath());

        try {
            $this->populateOverview($book, $data);
            $this->populateFinalResults($book, $data, $assetDirectory);
            $this->populateStepLadder($book, $data, $assetDirectory);
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
            ->map(fn (Collection $rows): string => $rows->sortBy('sort_order')->pluck('name')->filter()->implode(' ／ '));

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

        $broadcast = trim((string) ($settings['broadcast_text'] ?? $tournament->broadcast ?? ''));
        $streaming = trim((string) ($settings['streaming_text'] ?? $tournament->streaming ?? ''));
        $venueDisplay = trim((string) ($settings['venue_display'] ?? ''));
        if ($venueDisplay === '') {
            $venueDisplay = trim((string) $tournament->venue_name);
        }

        $postalCode = trim((string) ($settings['venue_postal_code'] ?? ''));
        $contactNumberParts = array_filter([
            $tournament->venue_tel ? 'ＴＥＬ '.$this->officialPhone($tournament->venue_tel) : null,
            $tournament->venue_fax ? 'ＦＡＸ '.$this->officialPhone($tournament->venue_fax) : null,
        ]);
        $contactParts = array_filter([
            trim($postalCode.' '.(string) $tournament->venue_address),
            implode('　　', $contactNumberParts),
        ]);

        $this->setNamed($book, 'RQ1_TITLE', $this->templateOverride(
            $book,
            'RQ_INPUT_OVERVIEW_TITLE',
            $this->displayTitle($tournament)
        ));
        $this->setNamed(
            $book,
            'RQ1_ENGLISH_TITLE',
            $this->templateOverride(
                $book,
                'RQ_INPUT_ENGLISH_TITLE',
                $settings['english_title'] ?? $this->englishTitle($tournament)
            )
        );
        $this->setNamed($book, 'RQ1_HOST', $this->templateOverride($book, 'RQ_INPUT_HOST', $organizations->get('host') ?: $tournament->host));
        $this->setNamed($book, 'RQ1_SUPPORT', $this->templateOverride($book, 'RQ_INPUT_SUPPORT', $organizations->get('support') ?: $tournament->support));
        $this->setNamed($book, 'RQ1_COOPERATION', $this->templateOverride($book, 'RQ_INPUT_COOPERATION', $organizations->get('cooperation') ?: ''));
        $this->setNamed($book, 'RQ1_AUTHORIZED', $this->templateOverride($book, 'RQ_INPUT_AUTHORIZED', $organizations->get('authorized') ?: $tournament->authorized_by));
        $this->setNamed($book, 'RQ1_DATES', $this->templateOverride($book, 'RQ_INPUT_DATES', $this->officialDateRange($tournament)));
        $this->setNamed($book, 'RQ1_VENUE', $this->templateOverride($book, 'RQ_INPUT_VENUE', $venueDisplay));
        $this->setNamed($book, 'RQ1_VENUE_CONTACT', $this->templateOverride($book, 'RQ_INPUT_VENUE_CONTACT', implode("\n", $contactParts)));
        $this->setNamed($book, 'RQ1_SCHEDULE', $this->templateOverride($book, 'RQ_INPUT_SCHEDULE', $schedule));
        $this->setNamed($book, 'RQ1_BROADCAST', $this->templateOverride($book, 'RQ_INPUT_BROADCAST', $broadcast));
        $this->setNamed($book, 'RQ1_STREAMING', $this->templateOverride($book, 'RQ_INPUT_STREAMING', $streaming));
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
            'RQ2_TITLE',
            $this->templateOverride($book, 'RQ_INPUT_RESULT_TITLE', $this->displayTitle($tournament))
        );
        $prizeTotal = array_sum(array_map('intval', $data['prizeDistributionMap'] ?? []));
        $this->setNamed(
            $book,
            'RQ2_PRIZE_TOTAL',
            $prizeTotal > 0
                ? '賞金総々額 '.number_format($prizeTotal).'円'
                : trim((string) $tournament->prize)
        );

        $resultRows = [
            ...range(7, 10),
            ...range(12, 15),
            ...range(17, 40),
        ];
        foreach ($resultRows as $rowNo) {
            foreach (['A', 'D', 'G', 'L', 'N', 'X', 'AA', 'AC'] as $column) {
                $sheet->setCellValue($column.$rowNo, null);
            }
        }

        foreach ($results->take(32) as $index => $result) {
            $rowNo = $resultRows[$index];
            $profile = $result->player ?: $result->bowler;
            $rank = (int) ($result->ranking ?? ($index + 1));
            $license = (string) ($result->pro_bowler_license_no ?? $profile?->license_no ?? '');
            $name = trim((string) ($profile?->name_kanji ?? $result->amateur_name ?? ''));

            $sheet->setCellValue('A'.$rowNo, $rank === 1 ? '優　勝' : '第'.$rank.'位');
            $sheet->setCellValueExplicit('D'.$rowNo, $this->displayLicenseDigits($license), DataType::TYPE_STRING);
            $sheet->setCellValue('G'.$rowNo, $name);
            $sheet->setCellValue('L'.$rowNo, $profile?->kibetsu ?? '');
            $sheet->setCellValue('N'.$rowNo, $result->pdf_affiliation_display ?? $result->affiliation_display ?? '-');
            $score = $this->resultScoreText($result, $rank, $data, $settings);
            $sheet->setCellValue('X'.$rowNo, $score);
            if (is_int($score) || is_float($score)) {
                $sheet->getStyle('X'.$rowNo.':Z'.$rowNo)->getNumberFormat()->setFormatCode('#,##0');
            }
            $sheet->setCellValue('AA'.$rowNo, (int) ($result->points ?? 0));

            $winnerPrizeDisplay = trim((string) $this->templateOverride(
                $book,
                'RQ_INPUT_WINNER_PRIZE',
                $settings['winner_prize_display'] ?? ''
            ));
            if ($rank === 1 && $winnerPrizeDisplay !== '') {
                $sheet->setCellValue('AC'.$rowNo, $winnerPrizeDisplay);
                $sheet->getStyle('AC'.$rowNo.':AG'.$rowNo)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('AC'.$rowNo.':AG'.$rowNo)->getFont()->setSize(7.6);
            } else {
                $prize = (int) (($data['prizeDistributionMap'][$rank] ?? null) ?? $result->prize_money ?? 0);
                $sheet->setCellValue('AC'.$rowNo, $prize);
                $sheet->getStyle('AC'.$rowNo.':AG'.$rowNo)->getNumberFormat()->setFormatCode('#,##0');
            }

            $sheet->getStyle('D'.$rowNo.':F'.$rowNo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('G'.$rowNo.':K'.$rowNo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('L'.$rowNo.':M'.$rowNo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.$rowNo.':AG'.$rowNo)->getFont()->setSize(10.2);
            $sheet->getStyle('N'.$rowNo.':W'.$rowNo)->getFont()->setSize(8.5);
            $sheet->getStyle('X'.$rowNo.':Z'.$rowNo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('AA'.$rowNo.':AB'.$rowNo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $winnerResult = $results->first(fn ($result): bool => (int) ($result->ranking ?? 0) === 1) ?: $results->first();
        $winner = $winnerResult?->player ?: $winnerResult?->bowler;
        if (! $winner instanceof ProBowler) {
            return;
        }

        $winner->loadMissing(['district', 'officialTitles']);
        $birthday = $winner->birthdate_public_is_private
            ? null
            : ($winner->birthdate_public ?: $winner->birthdate);
        $eventDate = Carbon::parse($tournament->end_date ?: $tournament->start_date ?: now());
        $age = $birthday ? (int) floor(Carbon::parse($birthday)->diffInYears($eventDate)) : null;
        $district = trim((string) ($winner->district?->name ?? $winner->district?->district_name ?? ''));

        $winnerProfile = trim(implode(' ', array_filter([
            $this->officialSpacedName((string) $winner->name_kanji),
            $age !== null ? $age.'歳' : null,
            $winner->kibetsu ? '（'.$winner->kibetsu.'期）' : null,
            $winner->pro_entry_year ? $winner->pro_entry_year.'年プロ入り' : null,
        ])));
        $winnerBio = trim(implode('　', array_filter([
            $birthday ? '生年月日:'.Carbon::parse($birthday)->format('Y年n月j日') : null,
            $winner->birthplace ? '出身地:'.$winner->birthplace : null,
            $winner->dominant_arm ? $winner->dominant_arm.'投げ' : null,
        ])));

        $this->setNamed($book, 'RQ2_WINNER_KANA', $this->halfWidthKana((string) $winner->name_kana));
        $this->setNamed(
            $book,
            'RQ2_WINNER_DISTRICT',
            $district !== '' ? 'JPBA所属地区：'.$district : ''
        );
        $this->setNamed($book, 'RQ2_WINNER_PROFILE', '優勝者 '.$winnerProfile);
        $this->setNamed($book, 'RQ2_WINNER_BIO', $winnerBio);
        $headline = (string) $this->templateOverride(
            $book,
            'RQ_INPUT_WINNER_HEADLINE',
            $settings['winner_headline'] ?? ('優勝 '.$winner->name_kanji)
        );
        [$headlineFirst, $headlineRest] = $this->splitFirstLine($headline);
        $this->setNamed($book, 'RQ2_WINNER_HEADLINE', $headlineFirst);
        $this->setNamed($book, 'RQ2_WINNER_SUBHEAD', $headlineRest);
        $this->setNamed(
            $book,
            'RQ2_WINNER_BALL',
            $this->templateOverride(
                $book,
                'RQ_INPUT_WINNER_BALL',
                $settings['winner_record'] ?? $this->winnerBallText($tournament, $winner)
            )
        );
        $this->setNamed(
            $book,
            'RQ2_AWARDS',
            $this->templateOverride(
                $book,
                'RQ_INPUT_AWARDS',
                $settings['awards_text'] ?? $this->buildAwardsText($tournament)
            )
        );
        $previous = (string) $this->templateOverride(
            $book,
            'RQ_INPUT_PREVIOUS_RESULTS',
            $settings['previous_results_text'] ?? ''
        );
        [$previousHeading, $previousBody] = $this->splitFirstLine($previous);
        $this->setNamed($book, 'RQ2_PREVIOUS_RESULTS_HEADING', $previousHeading);
        $this->setNamed($book, 'RQ2_PREVIOUS_RESULTS', $previousBody);
        $this->setNamed(
            $book,
            'RQ2_PERFECT',
            $this->templateOverride(
                $book,
                'RQ_INPUT_PERFECT',
                $settings['perfect_text'] ?? $this->buildPerfectText($tournament)
            )
        );
        $this->setNamed(
            $book,
            'RQ2_AMATEUR',
            $this->templateOverride(
                $book,
                'RQ_INPUT_AMATEUR',
                $settings['amateur_text'] ?? $this->buildAmateurText($tournament)
            )
        );

        $photo = $this->resolveProfilePhotoPath($winner, $assetDirectory);
        if ($photo !== null) {
            $this->setNamed($book, 'RQ2_WINNER_PHOTO_ANCHOR', '');
            $this->addDrawingAtNamedRange($book, 'RQ2_WINNER_PHOTO_ANCHOR', $photo, 82, 113, false);
        }
    }

    private function populateStepLadder(Spreadsheet $book, array $data, string $assetDirectory): void
    {
        $tournament = $data['tournament'];
        $settings = $this->formatSettings($tournament);
        $results = collect($data['results'] ?? [])->sortBy('ranking')->values();
        $topFour = $results->take(4)->values();
        $scoreSheets = collect($data['matchScoreSheets'] ?? [])->sortBy('match_order')->values();

        $this->setNamed(
            $book,
            'RQ3_TITLE',
            $this->templateOverride($book, 'RQ_INPUT_RESULT_TITLE', $this->displayTitle($tournament))
        );
        $r1 = $scoreSheets->first(fn ($sheet): bool => str_contains((string) $sheet->match_label, '4位'))
            ?: $scoreSheets->get(0);
        $r2 = $scoreSheets->first(fn ($sheet): bool => str_contains((string) $sheet->match_label, '3位'))
            ?: $scoreSheets->get(1);
        $final = $scoreSheets->first(fn ($sheet): bool => str_contains((string) $sheet->match_label, '優勝'))
            ?: $scoreSheets->get(2);

        $winnerResult = $topFour->get(0);
        $winner = $winnerResult?->player ?: $winnerResult?->bowler;
        $winnerName = trim((string) ($winner?->name_kanji ?? $winnerResult?->amateur_name ?? ''));
        $winnerKana = trim((string) ($winner?->name_kana ?? ''));
        $winnerNote = (string) $this->templateOverride(
            $book,
            'RQ_INPUT_STEP_LADDER_NOTE',
            $settings['step_ladder_winner_note'] ?? $settings['winner_headline'] ?? ''
        );
        $bracketLayout = [
            'seeds' => $topFour->map(function ($result): array {
                $profile = $result?->player ?: $result?->bowler;

                return [
                    'pro_bowler_id' => $profile?->id,
                    'display_name' => trim((string) ($profile?->name_kanji ?? $result?->amateur_name ?? '')),
                    'period' => trim((string) ($profile?->kibetsu ?? '')),
                ];
            })->all(),
            'scores' => [
                'round1_top' => $this->scoreForResult($r1, $topFour->get(2)),
                'round1_bottom' => $this->scoreForResult($r1, $topFour->get(3)),
                'round2_top' => $this->scoreForResult($r2, $topFour->get(1)),
                'round2_bottom' => $this->scoreForResult($r2, $topFour->get(2)),
                'final_top' => $this->scoreForResult($final, $topFour->get(0)),
                'final_bottom' => $this->scoreForResult($final, $topFour->get(1)),
            ],
            'champion' => [
                'display_name' => $winnerName,
                'name_kana' => $winnerKana,
            ],
            'winner_note' => $winnerNote,
        ];
        $bracketImage = $this->stepLadderBracketImageService->generateDataUri(
            $tournament,
            (array) ($data['stepLadderPdf'] ?? []),
            [
                'layout' => 'rokko_queens',
                'layout_data' => $bracketLayout,
            ]
        );
        if (is_string($bracketImage) && $bracketImage !== '') {
            $bracketPath = $this->writeDataUriImage($bracketImage, $assetDirectory);
            $this->addDrawingAtNamedRange(
                $book,
                'RQ3_BRACKET_IMAGE_ANCHOR',
                $bracketPath,
                674,
                219
            );
        }

        $topLane = (string) $this->templateOverride(
            $book,
            'RQ_INPUT_TV_TOP_LANE',
            $settings['tv_final_top_lane'] ?? '33L'
        );
        $bottomLane = (string) $this->templateOverride(
            $book,
            'RQ_INPUT_TV_BOTTOM_LANE',
            $settings['tv_final_bottom_lane'] ?? '34L'
        );
        $images = collect($this->matchScoreSheetImageService->generateDataUris(
            $scoreSheets,
            [
                'layout' => 'rokko_queens',
                'top_lane' => $topLane,
                'bottom_lane' => $bottomLane,
            ]
        ));
        $assignments = [
            'RQ3_FINAL_IMAGE_ANCHOR' => $images->first(fn (array $image): bool => str_contains((string) ($image['match_label'] ?? ''), '優勝')),
            'RQ3_R2_IMAGE_ANCHOR' => $images->first(fn (array $image): bool => str_contains((string) ($image['match_label'] ?? ''), '3位')),
            'RQ3_R1_IMAGE_ANCHOR' => $images->first(fn (array $image): bool => str_contains((string) ($image['match_label'] ?? ''), '4位')),
        ];

        foreach ($assignments as $range => $image) {
            if (! is_array($image) || empty($image['image'])) {
                continue;
            }
            $path = $this->writeDataUriImage((string) $image['image'], $assetDirectory);
            $this->addDrawingAtNamedRange($book, $range, $path, 674, 219);
        }
    }

    private function resultScoreText(object $result, int $rank, array $data, array $settings): string|int|null
    {
        if ($rank <= 4) {
            return match ($rank) {
                1 => '別紙TV決勝',
                2 => 'ｽﾃｯﾌﾟﾗﾀﾞｰ表',
                4 => '参照',
                default => '',
            };
        }

        if ($rank <= 8) {
            $overrides = (array) ($settings['round_robin_score_overrides'] ?? []);
            $override = $overrides[$rank] ?? $overrides[(string) $rank] ?? null;
            if ($override !== null && trim((string) $override) !== '') {
                return (string) $override;
            }

            $profile = $result->player ?: $result->bowler;
            $key = $this->participantKey(
                (string) ($result->pro_bowler_license_no ?? $profile?->license_no ?? ''),
                (string) ($profile?->name_kanji ?? $result->amateur_name ?? '')
            );
            $player = collect($data['roundRobinPdf']['players'] ?? [])->first(function (array $row) use ($key): bool {
                return $this->participantKey(
                    (string) ($row['license_no'] ?? ''),
                    (string) ($row['display_name'] ?? '')
                ) === $key;
            });

            if (is_array($player)) {
                $games = (int) ($player['carry_games'] ?? 0)
                    + (int) ($data['roundRobinPdf']['meta']['round_robin_games'] ?? 0);
                $score = (int) ($player['overall_total_points'] ?? 0) - ($games * 200);

                return ($score >= 0 ? '+' : '').number_format($score);
            }
        }

        return $result->total_pin ?? null;
    }

    private function scoreForResult($scoreSheet, $result): ?int
    {
        if ($scoreSheet === null || $result === null) {
            return null;
        }

        $profile = $result->player ?: $result->bowler;
        $key = $this->participantKey(
            (string) ($result->pro_bowler_license_no ?? $profile?->license_no ?? ''),
            (string) ($profile?->name_kanji ?? $result->amateur_name ?? '')
        );

        $player = collect($scoreSheet->players ?? [])->first(function ($row) use ($key): bool {
            return $this->participantKey(
                (string) ($row->pro_bowler_license_no ?? ''),
                (string) ($row->display_name ?? '')
            ) === $key;
        });

        return $player ? (int) $player->final_score : null;
    }

    private function winnerBallText($tournament, ProBowler $winner): string
    {
        $card = collect($tournament->result_cards ?? [])->first(function (array $row) use ($winner): bool {
            $player = preg_replace('/\s+/u', '', (string) ($row['player'] ?? ''));
            $winnerName = preg_replace('/\s+/u', '', (string) $winner->name_kanji);

            return $player === '' || $player === $winnerName;
        });

        return trim((string) ($card['balls'] ?? ''));
    }

    private function buildAwardsText($tournament): string
    {
        return collect($tournament->award_highlights ?? [])
            ->filter(fn (array $award): bool => (string) ($award['type'] ?? '') !== 'perfect')
            ->map(fn (array $award): string => trim(implode(' ', array_filter([
                $award['title'] ?? null,
                $award['player'] ?? null,
                $award['note'] ?? null,
            ]))))
            ->filter()
            ->implode("\n");
    }

    private function buildPerfectText($tournament): string
    {
        return collect($tournament->award_highlights ?? [])
            ->filter(fn (array $award): bool => (string) ($award['type'] ?? '') === 'perfect')
            ->map(fn (array $award): string => trim(implode(' ', array_filter([
                $award['title'] ?? 'パーフェクトゲーム',
                $award['player'] ?? null,
                $award['game'] ?? null,
                $award['lane'] ?? null,
                $award['note'] ?? null,
            ]))))
            ->filter()
            ->implode("\n");
    }

    private function buildAmateurText($tournament): string
    {
        $card = collect($tournament->result_cards ?? [])->first(
            fn (array $row): bool => str_contains((string) ($row['title'] ?? ''), 'アマ')
        );
        if (! is_array($card)) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $card['title'] ?? null,
            $card['player'] ?? null,
            $card['note'] ?? null,
        ])));
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
        int $height,
        bool $resizeProportional = true
    ): void {
        $defined = $book->getDefinedName($namedRange);
        if ($defined === null || $defined->getWorksheet() === null) {
            throw new RuntimeException('画像差し込み先の名前付き範囲がありません: '.$namedRange);
        }

        $address = str_replace('$', '', $defined->getValue());
        if (str_contains($address, '!')) {
            $address = substr($address, strrpos($address, '!') + 1);
        }
        $coordinate = explode(':', trim($address, "'"))[0];

        $drawing = new Drawing;
        $drawing->setPath($imagePath);
        $drawing->setCoordinates($coordinate);
        $drawing->setOffsetX(0);
        $drawing->setOffsetY(0);
        $drawing->setResizeProportional($resizeProportional);
        if ($resizeProportional) {
            $drawing->setWidth($width);
        } else {
            $drawing->setWidth($width);
            $drawing->setHeight($height);
        }
        $drawing->setWorksheet($defined->getWorksheet());
    }

    private function writeDataUriImage(string $dataUri, string $assetDirectory, bool $cropWhiteMargins = false): string
    {
        if (! preg_match('/^data:image\/(png|jpeg|jpg);base64,(.+)$/s', $dataUri, $matched)) {
            throw new RuntimeException('スコア表画像の形式が不正です。');
        }

        $binary = base64_decode($matched[2], true);
        if ($binary === false) {
            throw new RuntimeException('スコア表画像を復号できません。');
        }

        if ($cropWhiteMargins && extension_loaded('gd')) {
            $binary = $this->cropWhiteMargins($binary);
        }

        $extension = strtolower($matched[1]) === 'png' ? 'png' : 'jpg';
        $path = $assetDirectory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8)).'.'.$extension;
        file_put_contents($path, $binary);

        return $path;
    }

    private function cropWhiteMargins(string $binary): string
    {
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return $binary;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $left = $width;
        $right = 0;
        $top = $height;
        $bottom = 0;

        for ($y = 0; $y < $height; $y += 2) {
            for ($x = 0; $x < $width; $x += 2) {
                $rgb = imagecolorat($image, $x, $y);
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;
                if ($red < 246 || $green < 246 || $blue < 246) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        if ($right <= $left || $bottom <= $top) {
            imagedestroy($image);

            return $binary;
        }

        $left = max(0, $left - 8);
        $top = max(0, $top - 8);
        $right = min($width - 1, $right + 8);
        $bottom = min($height - 1, $bottom + 8);
        $cropped = imagecrop($image, [
            'x' => $left,
            'y' => $top,
            'width' => $right - $left + 1,
            'height' => $bottom - $top + 1,
        ]);
        imagedestroy($image);

        if ($cropped === false) {
            return $binary;
        }

        ob_start();
        imagepng($cropped);
        $output = (string) ob_get_clean();
        imagedestroy($cropped);

        return $output !== '' ? $output : $binary;
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

            $url = preg_match('~^https?://~i', $path) ? $path : null;
            if ($url === null && str_starts_with($path, '/')) {
                $profileUrl = trim((string) $winner->official_profile_url);
                $scheme = parse_url($profileUrl, PHP_URL_SCHEME) ?: 'https';
                $host = parse_url($profileUrl, PHP_URL_HOST) ?: 'www.jpba1.jp';
                $url = $scheme.'://'.$host.$path;
            }

            if ($url === null) {
                continue;
            }

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

        return null;
    }

    private function officialDateRange($tournament): string
    {
        if (! $tournament->start_date) {
            return '';
        }

        $start = Carbon::parse($tournament->start_date);
        $end = Carbon::parse($tournament->end_date ?: $tournament->start_date);
        $eraYear = (int) $start->year >= 2019 ? (int) $start->year - 2018 : (int) $start->year;

        return sprintf(
            '%d年(令和%d年)　%d月%d日(%s) ～ %d日(%s)',
            $start->year,
            $eraYear,
            $start->month,
            $start->day,
            $this->weekday($start),
            $end->day,
            $this->weekday($end)
        );
    }

    private function weekday(Carbon $date): string
    {
        return ['日', '月', '火', '水', '木', '金', '土'][(int) $date->dayOfWeek];
    }

    private function englishTitle($tournament): string
    {
        $match = [];
        preg_match('/第(\d+)回/u', (string) $tournament->name, $match);
        $number = (int) ($match[1] ?? 0);
        $suffix = match ($number % 100) {
            11, 12, 13 => 'th',
            default => match ($number % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            },
        };

        return ($number > 0 ? $number.$suffix.' ' : '')."ROKKO QUEEN's OPEN TOURNAMENT";
    }

    private function officialPhone(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^(\d{2,4})-(\d{2,4})-(\d{4})$/', $value, $matched)) {
            return mb_convert_kana($matched[1], 'N')
                .'（'.mb_convert_kana($matched[2], 'N').'）'
                .mb_convert_kana($matched[3], 'N');
        }

        return mb_convert_kana($value, 'N');
    }

    private function participantKey(string $license, string $name): string
    {
        $digits = $this->licenseDigits($license);
        if ($digits !== '') {
            return 'license:'.(ltrim($digits, '0') ?: '0');
        }

        return 'name:'.(preg_replace('/\s+/u', '', trim($name)) ?? '');
    }

    private function licenseDigits(string $license): string
    {
        return preg_replace('/\D+/', '', strtoupper(trim($license))) ?: '';
    }

    private function displayLicenseDigits(string $license): string
    {
        $digits = $this->licenseDigits($license);

        return $digits === '' ? '' : (ltrim($digits, '0') ?: '0');
    }

    private function formatSettings($tournament): array
    {
        $snapshot = is_array($tournament->template_snapshot) ? $tournament->template_snapshot : [];
        $settings = $snapshot['result_format'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    private function displayTitle($tournament): string
    {
        $title = trim((string) $tournament->name);

        return function_exists('mb_convert_kana')
            ? mb_convert_kana($title, 'N', 'UTF-8')
            : $title;
    }

    /** @return array{0:string,1:string} */
    private function splitFirstLine(string $value): array
    {
        $lines = preg_split('/\R/u', trim($value), 2) ?: [];

        return [
            trim((string) ($lines[0] ?? '')),
            trim((string) ($lines[1] ?? '')),
        ];
    }

    private function officialSpacedName(string $name): string
    {
        $name = trim(preg_replace('/[\s　]+/u', '', $name) ?? $name);
        if ($name === '' || str_contains($name, '･') || str_contains($name, '・')) {
            return $name;
        }

        $characters = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name];
        if (count($characters) === 4) {
            return $characters[0].$characters[1].'　'.$characters[2].$characters[3];
        }

        return $name;
    }

    private function halfWidthKana(string $value): string
    {
        $value = trim($value);

        return function_exists('mb_convert_kana')
            ? mb_convert_kana($value, 'kV', 'UTF-8')
            : $value;
    }

    private function safeName(string $name, string $extension): string
    {
        $name = preg_replace('/[\\\\\\/:*?"<>|]+/u', '_', $name) ?: 'tournament_results';
        $name = preg_replace('/\.(pdf|xlsx)$/i', '', $name) ?: 'tournament_results';

        return $name.'.'.$extension;
    }
}
