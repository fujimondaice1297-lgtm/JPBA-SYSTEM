@php
    $scoreImages = isset($matchScoreSheetImages) && is_array($matchScoreSheetImages)
        ? array_values($matchScoreSheetImages)
        : [];

    $firstScoreImage = $scoreImages[0] ?? null;
    $remainingScoreImages = count($scoreImages) > 1 ? array_slice($scoreImages, 1) : [];

    $scoreHeading = function ($scoreSheetImage, int $index) {
        $label = trim((string) ($scoreSheetImage['match_label'] ?? ''));
        return $label !== '' ? $label : ($index === 0 ? '優勝決定戦' : 'スコア表');
    };

    $resultRows = collect($results ?? []);

    $jpbaLogoPath = public_path('images/jpba_logo.png');
    $jpbaLogoSrc = null;
    if (is_file($jpbaLogoPath) && is_readable($jpbaLogoPath)) {
        $mime = function_exists('mime_content_type') ? mime_content_type($jpbaLogoPath) : null;
        if (!is_string($mime) || $mime === '') {
            $mime = 'image/png';
        }
        $imageBinary = file_get_contents($jpbaLogoPath);
        if (is_string($imageBinary) && $imageBinary !== '') {
            $jpbaLogoSrc = 'data:' . $mime . ';base64,' . base64_encode($imageBinary);
        }
    }

    $valueOf = function ($source, array $keys, $default = '') {
        foreach ($keys as $key) {
            if (is_object($source) && isset($source->{$key}) && trim((string) $source->{$key}) !== '') {
                return trim((string) $source->{$key});
            }
            if (is_array($source) && isset($source[$key]) && trim((string) $source[$key]) !== '') {
                return trim((string) $source[$key]);
            }
        }
        return $default;
    };

    $normalizeText = function ($text): string {
        return preg_replace('/\s+/u', '', trim((string) $text)) ?? trim((string) $text);
    };

    $formatPersonName = function ($name) use ($normalizeText): string {
        $name = trim((string) $name);
        if ($name === '') {
            return '-';
        }

        if (preg_match('/[\s　]+/u', $name)) {
            $parts = preg_split('/[\s　]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
            return !empty($parts) ? implode('　', $parts) : $name;
        }

        $compact = $normalizeText($name);
        $length = mb_strlen($compact, 'UTF-8');
        if ($length <= 2) {
            return $compact;
        }

        // 日本語氏名の最低限の安全策。
        // 4文字は 2+2、5文字以上は 3+残りを基本にし、PDF内の詰まりを防ぐ。
        $splitAt = $length >= 5 ? 3 : 2;
        return mb_substr($compact, 0, $splitAt, 'UTF-8') . '　' . mb_substr($compact, $splitAt, null, 'UTF-8');
    };

    $formatPeriodDigits = function ($value): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '-';
        }
        $digits = preg_replace('/[^0-9０-９]+/u', '', $text) ?? '';
        if ($digits !== '') {
            return mb_convert_kana($digits, 'n', 'UTF-8');
        }
        return $text;
    };

    $tournamentName = isset($tournament) ? trim((string) ($tournament->name ?? '')) : '';
    $flowType = isset($tournament) ? trim((string) ($tournament->result_flow_type ?? '')) : '';
    $venueText = '';

    if (isset($tournament)) {
        $venueText = $valueOf($tournament, [
            'venue_name',
            'venue',
            'bowling_center_name',
            'center_name',
            'place',
            'location',
        ], '');
    }

    $dateText = '';
    if (isset($tournament)) {
        $start = $tournament->start_date ?? null;
        $end = $tournament->end_date ?? null;
        if ($start && $end && (string) $start !== (string) $end) {
            $dateText = (string) $start . '～' . (string) $end;
        } elseif ($start) {
            $dateText = (string) $start;
        } elseif ($end) {
            $dateText = (string) $end;
        }
    }

    $shootoutSettings = [];
    if (isset($tournament)) {
        $rawShootoutSettings = $tournament->shootout_settings ?? [];
        if (is_array($rawShootoutSettings)) {
            $shootoutSettings = $rawShootoutSettings;
        } elseif (is_string($rawShootoutSettings) && trim($rawShootoutSettings) !== '') {
            $decodedShootoutSettings = json_decode($rawShootoutSettings, true);
            $shootoutSettings = is_array($decodedShootoutSettings) ? $decodedShootoutSettings : [];
        }
    }

    $singleEliminationSettings = [];
    if (isset($tournament)) {
        $rawSingleSettings = $tournament->single_elimination_seed_settings ?? [];
        if (is_array($rawSingleSettings)) {
            $singleEliminationSettings = $rawSingleSettings;
        } elseif (is_string($rawSingleSettings) && trim($rawSingleSettings) !== '') {
            $decodedSingleSettings = json_decode($rawSingleSettings, true);
            $singleEliminationSettings = is_array($decodedSingleSettings) ? $decodedSingleSettings : [];
        }
    }

    $stageProgress = $shootoutSettings['stage_progress'] ?? [];
    $stageProgress = is_array($stageProgress) ? $stageProgress : [];

    $readStageInt = function (string $key, ?int $default = null) use ($stageProgress) {
        $value = $stageProgress[$key] ?? null;
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return (int) $value;
    };

    $autoSemifinalQualifierCount = function (?int $playerCount): ?int {
        if ($playerCount === null || $playerCount <= 0) {
            return null;
        }
        $half = (int) ceil($playerCount / 2);
        return $half % 2 === 0 ? $half : $half + 1;
    };

    $prelimPlayerCount = $readStageInt('prelim_player_count');
    $prelimGameCount = $readStageInt('prelim_game_count', 8);
    $prelimQualifierCount = $readStageInt('prelim_qualifier_count', $autoSemifinalQualifierCount($prelimPlayerCount));
    $semifinalGameCount = $readStageInt('semifinal_game_count', 4);
    $semifinalTotalGameCount = $readStageInt('semifinal_total_game_count', ($prelimGameCount ?? 8) + ($semifinalGameCount ?? 4));
    $semifinalQualifierCount = $readStageInt('semifinal_qualifier_count', isset($tournament) && isset($tournament->shootout_qualifier_count) ? (int) $tournament->shootout_qualifier_count : 8);

    $isShootoutFlow = in_array($flowType, [
        'prelim_to_shootout_to_final',
        'prelim_to_quarterfinal_to_shootout_to_final',
        'prelim_to_semifinal_to_shootout_to_final',
    ], true);
    $isSingleEliminationFlow = str_contains($flowType, 'single_elimination');

    // PDFは「大会カテゴリ」と「決勝方式」を分けて扱う。
    // 例：シーズントライアル大会カテゴリ + シュートアウト決勝方式。
    $finalFormat = $isSingleEliminationFlow ? 'single_elimination' : ($isShootoutFlow ? 'shootout' : 'standard');

    $seasonTrialFlag = false;
    $settingsText = json_encode($shootoutSettings, JSON_UNESCAPED_UNICODE) ?: '';
    if (str_contains($tournamentName, 'シーズントライアル') || str_contains($settingsText, 'シーズントライアル')) {
        $seasonTrialFlag = true;
    }
    if (!$seasonTrialFlag && !empty($stageProgress) && $isShootoutFlow) {
        // stage_progress は現状、シーズントライアル公式PDF寄せの進行設定として使っている。
        $seasonTrialFlag = true;
    }

    // pdfModeは「どの外枠Bladeへ入るか」。
    // シーズントライアルの場合でも、決勝方式は $finalFormat で別管理する。
    $pdfMode = $seasonTrialFlag ? 'season_trial' : ($isSingleEliminationFlow ? 'single_elimination' : ($isShootoutFlow ? 'shootout' : 'standard'));
    $pdfCategory = $seasonTrialFlag ? 'season_trial' : 'standard';
    $isSeasonTrialPdf = $pdfMode === 'season_trial';

    $stageNumber = fn (?int $value): string => $value !== null && $value > 0 ? number_format($value) : '-';

    $officialMainTitle = $tournamentName !== '' ? $tournamentName : '大会成績';
    $officialSeriesTitle = $isSeasonTrialPdf ? ($shootoutSettings['series_title'] ?? 'ＪＰＢＡシーズントライアル２０２６') : '';
    $officialSeasonTitle = $isSeasonTrialPdf ? ($shootoutSettings['season_title'] ?? 'ウィンターシリーズ') : '';
    $officialVenueTitle = $venueText !== '' ? $venueText : '会場';

    $finalQualifierCount = $isSingleEliminationFlow
        ? (int) ($tournament->single_elimination_qualifier_count ?? $semifinalQualifierCount ?? 0)
        : (int) ($semifinalQualifierCount ?? 0);
    if ($finalQualifierCount <= 0) {
        $finalQualifierCount = (int) ($semifinalQualifierCount ?: 8);
    }

    $finalFormatLabel = $finalFormat === 'single_elimination' ? 'トーナメント方式' : ($finalFormat === 'shootout' ? 'シュートアウト方式' : 'トータルピン方式');

    $seriesTitle = trim(implode(' ', array_filter([$officialMainTitle, $officialSeriesTitle, $officialSeasonTitle])));

    $resolveName = function ($result) use ($valueOf, $formatPersonName) {
        $rawName = optional($result->player)->name_kanji
            ?? optional($result->bowler)->name_kanji
            ?? $valueOf($result, ['player_name', 'name', 'display_name', 'amateur_name'], '-');
        return $formatPersonName($rawName);
    };

    $resolveLicense = function ($result) use ($valueOf) {
        $raw = $valueOf($result, ['pro_bowler_license_no', 'license_no'], '');
        if ($raw === '') {
            $raw = optional($result->player)->license_no ?? optional($result->bowler)->license_no ?? '';
        }
        $license = preg_replace('/\s+/', '', trim((string) $raw)) ?? trim((string) $raw);
        return $license === '' ? '-' : mb_substr($license, -4);
    };

    $resolveRank = function ($result) use ($valueOf) {
        $rank = $valueOf($result, ['ranking', 'rank', 'position', 'placing', 'result_rank', 'order_no'], '-');
        if (!is_numeric($rank)) {
            return $rank;
        }
        $rank = (int) $rank;
        return match ($rank) {
            1 => '優　勝',
            2 => '第２位',
            3 => '第３位',
            4 => '第４位',
            5 => '第５位',
            6 => '第６位',
            7 => '第７位',
            8 => '第８位',
            default => '第' . $rank . '位',
        };
    };

    $resolvePeriod = function ($result) use ($valueOf, $formatPeriodDigits) {
        $period = $valueOf($result, ['bowler_period_label', 'period_label', 'period', 'generation'], '');
        if ($period === '') {
            $period = optional($result->player)->period_label
                ?? optional($result->bowler)->period_label
                ?? optional($result->player)->period
                ?? optional($result->bowler)->period
                ?? '';
        }
        return $formatPeriodDigits($period);
    };

    $resolveBelong = function ($result) use ($valueOf) {
        $belong = $valueOf($result, [
            'pdf_affiliation_display', 'affiliation_display', 'affiliation', 'belonging', 'organization_name',
            'organization', 'sponsor', 'sponsor_name', 'company_name', 'club_name', 'center_name', 'shop_name',
        ], '');
        if ($belong === '') {
            $player = $result->player ?? $result->bowler ?? null;
            $organizationName = trim((string) (optional($player)->organization_name ?? optional($player)->affiliation ?? optional($player)->belonging ?? optional($player)->organization ?? optional($player)->sponsor ?? ''));
            $equipmentContract = trim((string) (optional($player)->equipment_contract ?? optional($player)->equipment ?? optional($player)->equipment_sponsor ?? ''));
            if ($organizationName !== '' && $equipmentContract !== '') {
                $belong = $organizationName . '/' . $equipmentContract;
            } elseif ($organizationName !== '') {
                $belong = $organizationName;
            } elseif ($equipmentContract !== '') {
                $belong = $equipmentContract;
            }
        }
        return trim((string) $belong) !== '' ? trim((string) $belong) : '-';
    };

    $belongTextClass = function ($text): string {
        $text = trim((string) $text);
        $width = function_exists('mb_strwidth') ? mb_strwidth($text, 'UTF-8') : strlen($text);
        if ($width >= 62) return 'belong-text belong-text-size-7';
        if ($width >= 54) return 'belong-text belong-text-size-6';
        if ($width >= 46) return 'belong-text belong-text-size-5';
        if ($width >= 38) return 'belong-text belong-text-size-4';
        if ($width >= 30) return 'belong-text belong-text-size-3';
        if ($width >= 22) return 'belong-text belong-text-size-2';
        return 'belong-text belong-text-size-1';
    };

    $resolveNumber = function ($result, array $keys, $default = 0) use ($valueOf) {
        $value = $valueOf($result, $keys, '');
        if ($value === '') return $default;
        $numeric = str_replace([',', '¥', '\\'], '', (string) $value);
        return is_numeric($numeric) ? (float) $numeric : $default;
    };

    $formatNumber = function ($value, int $decimals = 0) {
        if ($value === null || $value === '') return '-';
        $numeric = str_replace([',', '¥', '\\'], '', (string) $value);
        if (!is_numeric($numeric)) return (string) $value;
        return number_format((float) $numeric, $decimals);
    };

    $formatPrize = function ($value) {
        if ($value === null || $value === '') return '-';
        $numeric = str_replace([',', '¥', '\\'], '', (string) $value);
        if (!is_numeric($numeric)) return (string) $value;
        return number_format((float) $numeric);
    };

    $pdfScoreSnapshots = [];
    if (isset($scoreSnapshots) && is_iterable($scoreSnapshots)) {
        foreach ($scoreSnapshots as $snapshotSet) {
            $snapshot = is_array($snapshotSet) ? ($snapshotSet['snapshot'] ?? null) : ($snapshotSet->snapshot ?? $snapshotSet);
            $rows = is_array($snapshotSet) ? ($snapshotSet['rows'] ?? []) : ($snapshotSet->rows ?? []);
            if ($snapshot) {
                $pdfScoreSnapshots[] = ['snapshot' => $snapshot, 'rows' => collect($rows)->values()];
            }
        }
    }

    // Blade側でも result_code ごとに1件だけに絞る。Controller側の保険。
    $latestByResultCode = [];
    foreach ($pdfScoreSnapshots as $snapshotSet) {
        $snapshot = $snapshotSet['snapshot'] ?? null;
        $code = trim((string) ($snapshot->result_code ?? ''));
        if ($code === '') continue;
        if (!isset($latestByResultCode[$code]) || (int) ($snapshot->id ?? 0) > (int) (($latestByResultCode[$code]['snapshot']->id ?? 0))) {
            $latestByResultCode[$code] = $snapshotSet;
        }
    }
    $orderedCodes = ['semifinal_total', 'prelim_total'];
    $pdfScoreSnapshots = [];
    foreach ($orderedCodes as $code) {
        if (isset($latestByResultCode[$code])) {
            $pdfScoreSnapshots[] = $latestByResultCode[$code];
        }
    }

    $gameScoreMap = [];
    $gameScoreRowsForPdf = collect();
    if (isset($tournament) && isset($tournament->id)) {
        try {
            $gameScoreRows = \Illuminate\Support\Facades\DB::table('game_scores')
                ->where('tournament_id', $tournament->id)
                ->whereIn('stage', ['予選', '準決勝'])
                ->orderBy('stage')
                ->orderBy('game_number')
                ->get();
            $gameScoreRowsForPdf = $gameScoreRows;
            foreach ($gameScoreRows as $gameScoreRow) {
                $stage = trim((string) ($gameScoreRow->stage ?? ''));
                $gameNumber = (int) ($gameScoreRow->game_number ?? 0);
                if ($stage === '' || $gameNumber <= 0) continue;
                $licenseKey = strtoupper(trim((string) ($gameScoreRow->license_number ?? '')));
                $nameKey = $normalizeText($gameScoreRow->name ?? '');
                if ($licenseKey !== '') $gameScoreMap[$stage]['license'][$licenseKey][$gameNumber] = (int) ($gameScoreRow->score ?? 0);
                if ($nameKey !== '') $gameScoreMap[$stage]['name'][$nameKey][$gameNumber] = (int) ($gameScoreRow->score ?? 0);
            }
        } catch (\Throwable $e) {
            $gameScoreMap = [];
        }
    }

    $normalizeLicenseKey = fn ($license): string => strtoupper(preg_replace('/\s+/u', '', trim((string) $license)) ?? trim((string) $license));
    $licenseTailKey = function ($license): string {
        $digits = preg_replace('/\D+/u', '', trim((string) $license)) ?? '';
        return $digits === '' ? '' : ltrim($digits, '0');
    };

    $snapshotMetaById = [];
    foreach ($pdfScoreSnapshots as $snapshotSetForMeta) {
        $snapshotForMeta = $snapshotSetForMeta['snapshot'] ?? null;
        $snapshotIdForMeta = (int) ($snapshotForMeta->id ?? 0);
        $definitionRaw = $snapshotForMeta->calculation_definition ?? null;
        $definition = is_string($definitionRaw) ? (json_decode($definitionRaw, true) ?: []) : (is_array($definitionRaw) ? $definitionRaw : []);
        foreach (($definition['participant_meta'] ?? []) as $licenseForMeta => $metaForLicense) {
            $licenseKeyForMeta = $normalizeLicenseKey($licenseForMeta);
            $tailKeyForMeta = $licenseTailKey($licenseForMeta);
            if ($snapshotIdForMeta > 0 && $licenseKeyForMeta !== '') $snapshotMetaById[$snapshotIdForMeta][$licenseKeyForMeta] = $metaForLicense;
            if ($snapshotIdForMeta > 0 && $tailKeyForMeta !== '') $snapshotMetaById[$snapshotIdForMeta]['tail:' . $tailKeyForMeta] = $metaForLicense;
        }
    }

    $proBowlerInfoById = [];
    $proBowlerInfoByLicense = [];
    $proBowlerInfoByTail = [];
    try {
        $proBowlerIds = [];
        $licenseCandidates = [];
        foreach ($resultRows as $result) {
            $id = $result->pro_bowler_id ?? optional($result->player)->id ?? optional($result->bowler)->id ?? null;
            if ($id) $proBowlerIds[] = (int) $id;
            $licenseCandidates[] = $result->pro_bowler_license_no ?? optional($result->player)->license_no ?? optional($result->bowler)->license_no ?? null;
        }
        foreach ($pdfScoreSnapshots as $snapshotSet) {
            foreach (($snapshotSet['rows'] ?? []) as $row) {
                $id = $row->pro_bowler_id ?? ($row['pro_bowler_id'] ?? null);
                if ($id) $proBowlerIds[] = (int) $id;
                $licenseCandidates[] = $row->pro_bowler_license_no ?? ($row['pro_bowler_license_no'] ?? null) ?? null;
            }
        }
        foreach ($gameScoreRowsForPdf as $gameScoreRow) {
            if (!empty($gameScoreRow->pro_bowler_id)) $proBowlerIds[] = (int) $gameScoreRow->pro_bowler_id;
            $licenseCandidates[] = $gameScoreRow->license_number ?? null;
        }
        $proBowlerIds = array_values(array_unique(array_filter($proBowlerIds)));
        $licenseCandidates = array_values(array_unique(array_filter(array_map($normalizeLicenseKey, $licenseCandidates))));
        $query = \Illuminate\Support\Facades\DB::table('pro_bowlers');
        if (count($proBowlerIds) > 0 || count($licenseCandidates) > 0) {
            $query->where(function ($q) use ($proBowlerIds, $licenseCandidates) {
                if (count($proBowlerIds) > 0) $q->whereIn('id', $proBowlerIds);
                if (count($licenseCandidates) > 0) $q->orWhereIn('license_no', $licenseCandidates);
            });
            foreach ($query->get() as $proBowlerInfo) {
                $proBowlerInfoById[(int) $proBowlerInfo->id] = $proBowlerInfo;
                $licenseKey = $normalizeLicenseKey($proBowlerInfo->license_no ?? '');
                if ($licenseKey !== '') $proBowlerInfoByLicense[$licenseKey] = $proBowlerInfo;
                $tailKey = $licenseTailKey($proBowlerInfo->license_no ?? '');
                if ($tailKey !== '') $proBowlerInfoByTail[$tailKey] = $proBowlerInfo;
            }
        }
    } catch (\Throwable $e) {
        $proBowlerInfoById = [];
        $proBowlerInfoByLicense = [];
        $proBowlerInfoByTail = [];
    }

    $snapshotValue = function ($row, array $keys, $default = '') {
        foreach ($keys as $key) {
            if (is_object($row) && isset($row->{$key}) && trim((string) $row->{$key}) !== '') return trim((string) $row->{$key});
            if (is_array($row) && isset($row[$key]) && trim((string) $row[$key]) !== '') return trim((string) $row[$key]);
        }
        return $default;
    };

    $snapshotLicenseRaw = fn ($row) => $snapshotValue($row, ['pro_bowler_license_no', 'license_number', 'license_no'], '');

    $snapshotMeta = function ($row) use ($snapshotValue, $snapshotLicenseRaw, $normalizeLicenseKey, $licenseTailKey, $snapshotMetaById) {
        $snapshotId = (int) $snapshotValue($row, ['snapshot_id'], 0);
        $license = $snapshotLicenseRaw($row);
        $licenseKey = $normalizeLicenseKey($license);
        $tailKey = $licenseTailKey($license);
        if ($snapshotId > 0 && $licenseKey !== '' && isset($snapshotMetaById[$snapshotId][$licenseKey])) return $snapshotMetaById[$snapshotId][$licenseKey];
        if ($snapshotId > 0 && $tailKey !== '' && isset($snapshotMetaById[$snapshotId]['tail:' . $tailKey])) return $snapshotMetaById[$snapshotId]['tail:' . $tailKey];
        return [];
    };

    $snapshotLicense = function ($row) use ($snapshotLicenseRaw) {
        $license = preg_replace('/\s+/u', '', trim((string) $snapshotLicenseRaw($row))) ?? trim((string) $snapshotLicenseRaw($row));
        return $license === '' ? '-' : mb_substr($license, -4);
    };

    $snapshotName = function ($row) use ($snapshotValue, $formatPersonName) {
        return $formatPersonName($snapshotValue($row, ['display_name', 'name', 'amateur_name'], '-'));
    };

    $snapshotInfo = function ($row) use ($snapshotValue, $snapshotLicenseRaw, $normalizeLicenseKey, $licenseTailKey, $proBowlerInfoById, $proBowlerInfoByLicense, $proBowlerInfoByTail) {
        $id = $snapshotValue($row, ['pro_bowler_id'], '');
        if ($id !== '' && isset($proBowlerInfoById[(int) $id])) return $proBowlerInfoById[(int) $id];
        $license = $snapshotLicenseRaw($row);
        $licenseKey = $normalizeLicenseKey($license);
        if ($licenseKey !== '' && isset($proBowlerInfoByLicense[$licenseKey])) return $proBowlerInfoByLicense[$licenseKey];
        $tailKey = $licenseTailKey($license);
        if ($tailKey !== '' && isset($proBowlerInfoByTail[$tailKey])) return $proBowlerInfoByTail[$tailKey];
        return null;
    };

    $infoValue = function ($info, array $keys, $default = '') {
        foreach ($keys as $key) {
            if (is_object($info) && isset($info->{$key}) && trim((string) $info->{$key}) !== '') return trim((string) $info->{$key});
        }
        return $default;
    };

    $snapshotPeriod = function ($row) use ($snapshotValue, $snapshotInfo, $infoValue, $snapshotMeta, $formatPeriodDigits) {
        $period = $snapshotValue($row, ['period', 'bowler_period_label', 'period_label', 'generation', 'kibetsu'], '');
        $meta = $snapshotMeta($row);
        if ($period === '' && is_array($meta) && !empty($meta['period'])) $period = (string) $meta['period'];
        if ($period === '') $period = $infoValue($snapshotInfo($row), ['kibetsu', 'period', 'term', 'generation', 'professional_generation', 'professional_period'], '');
        return $formatPeriodDigits($period);
    };

    $snapshotArm = function ($row) use ($snapshotValue, $snapshotInfo, $infoValue, $snapshotMeta) {
        $arm = $snapshotValue($row, ['arm', 'dominant_arm', 'dominant_hand', 'throwing_arm'], '');
        $meta = $snapshotMeta($row);
        if ($arm === '' && is_array($meta) && !empty($meta['arm'])) $arm = (string) $meta['arm'];
        if ($arm === '') $arm = $infoValue($snapshotInfo($row), ['dominant_arm', 'dominant_hand', 'throwing_arm', 'handedness', 'arm'], '');
        $arm = trim((string) $arm);
        if ($arm === '') return '-';
        if (str_contains($arm, 'サムレス') || str_contains($arm, '両手')) return $arm;
        if (str_contains($arm, '左')) return '左';
        if (str_contains($arm, '右')) return '右';
        return $arm;
    };

    $snapshotBelong = function ($row) use ($snapshotInfo, $infoValue, $snapshotMeta) {
        $meta = $snapshotMeta($row);
        if (is_array($meta) && !empty($meta['affiliation'])) return (string) $meta['affiliation'];
        $info = $snapshotInfo($row);
        $organization = $infoValue($info, ['organization_name', 'affiliation', 'belonging', 'organization', 'sponsor', 'company_name', 'club_name', 'center_name'], '');
        $equipment = $infoValue($info, ['equipment_contract', 'equipment', 'equipment_sponsor'], '');
        if ($organization !== '' && $equipment !== '') return $organization . '/' . $equipment;
        if ($organization !== '') return $organization;
        if ($equipment !== '') return $equipment;
        return '-';
    };

    $snapshotBelongClass = function (string $text): string {
        $length = mb_strlen($text);
        if ($length >= 34) return 'snapshot-belong-cell extra-long-text';
        if ($length >= 22) return 'snapshot-belong-cell long-text';
        return 'snapshot-belong-cell';
    };

    $snapshotScoreFor = function ($stage, $row, int $gameNumber) use ($snapshotValue, $snapshotName, $normalizeText, $gameScoreMap) {
        $license = strtoupper(trim((string) $snapshotValue($row, ['pro_bowler_license_no', 'license_number', 'license_no'], '')));
        if ($license !== '' && isset($gameScoreMap[$stage]['license'][$license][$gameNumber])) return $gameScoreMap[$stage]['license'][$license][$gameNumber];
        $nameKey = $normalizeText($snapshotName($row));
        if ($nameKey !== '' && isset($gameScoreMap[$stage]['name'][$nameKey][$gameNumber])) return $gameScoreMap[$stage]['name'][$nameKey][$gameNumber];
        return null;
    };

    $snapshotTitle = function ($snapshot) {
        $resultCode = trim((string) ($snapshot->result_code ?? ''));
        $resultName = trim((string) ($snapshot->result_name ?? ''));
        if ($resultName !== '') return $resultName;
        return match ($resultCode) {
            'prelim_total' => '予選通算成績',
            'semifinal_total' => '準決勝通算成績',
            default => '大会成績',
        };
    };

    $scoreTextClass = fn ($score): string => is_numeric($score) && (int) $score >= 300 ? 'score-red' : '';

    $snapshotLicenseKey = function ($row) use ($snapshotLicenseRaw, $normalizeLicenseKey, $licenseTailKey): string {
        $raw = $snapshotLicenseRaw($row);
        $key = $normalizeLicenseKey($raw);
        return $key !== '' ? $key : $licenseTailKey($raw);
    };

    $prelimRankByLicense = [];
    foreach ($pdfScoreSnapshots as $snapshotSetForRank) {
        $snapshotForRank = $snapshotSetForRank['snapshot'] ?? null;
        if (($snapshotForRank->result_code ?? '') !== 'prelim_total') continue;
        foreach (($snapshotSetForRank['rows'] ?? []) as $prelimRankRow) {
            $key = $snapshotLicenseKey($prelimRankRow);
            $rank = $snapshotValue($prelimRankRow, ['ranking'], '');
            if ($key !== '' && is_numeric($rank)) $prelimRankByLicense[$key] = (int) $rank;
        }
    }

    $snapshotPrelimRank = function ($row) use ($snapshotLicenseKey, $prelimRankByLicense) {
        $key = $snapshotLicenseKey($row);
        return $key !== '' && isset($prelimRankByLicense[$key]) ? $prelimRankByLicense[$key] : null;
    };

    $stepPointLabelForSemifinalRank = function ($rank, $points = null) use ($semifinalQualifierCount, $prelimQualifierCount) {
        if (!is_numeric($rank)) return '-';
        $rank = (int) $rank;
        $finalistCount = (int) ($semifinalQualifierCount ?: 8);
        if ($rank <= $finalistCount) return null;
        if ($points !== null && $points !== '' && is_numeric($points)) return (int) $points . 'P';
        $point = (($prelimQualifierCount ?? 0) > 0) ? max(1, (int) $prelimQualifierCount - $rank + 1) : max(1, $finalistCount + 1 + ($finalistCount + 2 - $rank));
        return $point . 'P';
    };
@endphp


@php
    view()->share([
        'shootoutPdf' => $shootoutPdf ?? null,
        'shootoutBracketImage' => $shootoutBracketImage ?? null,
        'matchScoreSheets' => $matchScoreSheets ?? collect(),
        'matchScoreSheetImages' => $matchScoreSheetImages ?? [],
        'singleEliminationPdf' => $singleEliminationPdf ?? null,
        'singleEliminationBracketImage' => $singleEliminationBracketImage ?? null,
        'scoreImages' => $scoreImages,
        'firstScoreImage' => $firstScoreImage,
        'remainingScoreImages' => $remainingScoreImages,
        'scoreHeading' => $scoreHeading,
        'resultRows' => $resultRows,
        'jpbaLogoSrc' => $jpbaLogoSrc,
        'venueText' => $venueText,
        'dateText' => $dateText,
        'pdfMode' => $pdfMode,
        'pdfCategory' => $pdfCategory,
        'finalFormat' => $finalFormat,
        'isSeasonTrialPdf' => $isSeasonTrialPdf,
        'isShootoutFlow' => $isShootoutFlow,
        'isSingleEliminationFlow' => $isSingleEliminationFlow,
        'stageNumber' => $stageNumber,
        'officialMainTitle' => $officialMainTitle,
        'officialSeriesTitle' => $officialSeriesTitle,
        'officialSeasonTitle' => $officialSeasonTitle,
        'officialVenueTitle' => $officialVenueTitle,
        'finalQualifierCount' => $finalQualifierCount,
        'finalFormatLabel' => $finalFormatLabel,
        'seriesTitle' => $seriesTitle,
        'resolveName' => $resolveName,
        'resolveLicense' => $resolveLicense,
        'resolveRank' => $resolveRank,
        'resolvePeriod' => $resolvePeriod,
        'resolveBelong' => $resolveBelong,
        'belongTextClass' => $belongTextClass,
        'resolveNumber' => $resolveNumber,
        'formatNumber' => $formatNumber,
        'formatPrize' => $formatPrize,
        'pdfScoreSnapshots' => $pdfScoreSnapshots,
        'prelimPlayerCount' => $prelimPlayerCount,
        'prelimGameCount' => $prelimGameCount,
        'prelimQualifierCount' => $prelimQualifierCount,
        'semifinalGameCount' => $semifinalGameCount,
        'semifinalTotalGameCount' => $semifinalTotalGameCount,
        'semifinalQualifierCount' => $semifinalQualifierCount,
        'snapshotValue' => $snapshotValue,
        'snapshotLicense' => $snapshotLicense,
        'snapshotName' => $snapshotName,
        'snapshotPeriod' => $snapshotPeriod,
        'snapshotArm' => $snapshotArm,
        'snapshotBelong' => $snapshotBelong,
        'snapshotBelongClass' => $snapshotBelongClass,
        'snapshotScoreFor' => $snapshotScoreFor,
        'snapshotTitle' => $snapshotTitle,
        'scoreTextClass' => $scoreTextClass,
        'snapshotLicenseKey' => $snapshotLicenseKey,
        'snapshotPrelimRank' => $snapshotPrelimRank,
        'stepPointLabelForSemifinalRank' => $stepPointLabelForSemifinalRank,
    ]);
@endphp
