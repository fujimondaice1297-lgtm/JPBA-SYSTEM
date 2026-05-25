<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Collection;

class TournamentLaneMovementService
{
    public function buildRows(Tournament $tournament, Collection $entries): array
    {
        $settings = $this->settings($tournament);
        $boxes = $this->boxes($settings);
        $dayBlocks = $settings['day_blocks'] ?? [];

        $slotByLane = [];
        $rows = [];

        foreach ($entries as $entry) {
            $lane = (int) $entry->lane;

            if ($lane < (int) $settings['lane_from'] || $lane > (int) $settings['lane_to']) {
                continue;
            }

            $slotByLane[$lane] = ($slotByLane[$lane] ?? 0) + 1;
            $slotNo = isset($entry->lane_slot) && (int) $entry->lane_slot > 0
                ? (int) $entry->lane_slot
                : $slotByLane[$lane];

            $bowler = $entry->bowler ?? null;
            $displayName = trim((string) ($entry->display_name ?? ($entry->name ?? ($bowler->name_kanji ?? ''))));
            $displayLicenseNo = trim((string) ($entry->display_license_no ?? ''));
            $licenseSource = $displayLicenseNo !== ''
                ? $displayLicenseNo
                : ($entry->pro_bowler_license_no ?? ($bowler->license_no ?? null));

            $startBoxIndex = $this->boxIndexForLane($lane, $settings);
            $gameLanes = $this->gameLanes($startBoxIndex, $settings, $boxes);
            $dayGameLanes = [];

            foreach ($dayBlocks as $block) {
                $key = (string) ($block['key'] ?? ('day_' . count($dayGameLanes)));
                $dayGameLanes[$key] = $this->gameLanesForBlock($startBoxIndex, $block, $settings, $boxes);
            }

            $rows[] = [
                'entry' => $entry,
                'bowler' => $bowler,
                'participant_type' => $entry->participant_type ?? null,
                'display_name' => $displayName,
                'display_license_no' => $displayLicenseNo,
                'start_lane_label' => $entry->lane_label ?? ($lane . 'L-' . $slotNo),
                'license_tail' => $this->licenseTail($licenseSource),
                'kibetsu' => $entry->kibetsu ?? ($entry->bowler_kibetsu ?? ($bowler->kibetsu ?? null)),
                'game_lanes' => $gameLanes,
                'day_game_lanes' => $dayGameLanes,
            ];
        }

        return [
            'settings' => $settings,
            'boxes' => $boxes,
            'day_blocks' => $dayBlocks,
            'rows' => $rows,
        ];
    }

    public function settings(Tournament $tournament): array
    {
        $raw = $tournament->lane_movement_settings ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            $raw = [];
        }

        $laneFrom = (int) ($raw['lane_from'] ?? $tournament->lane_from ?? 1);
        $laneTo = (int) ($raw['lane_to'] ?? $tournament->lane_to ?? $laneFrom);
        $boxWidth = max(1, (int) ($raw['box_width'] ?? 2));
        $games = max(1, (int) ($raw['games'] ?? 1));
        $startTime = trim((string) ($raw['start_time'] ?? ''));
        $regularMoveBoxes = max(0, (int) ($raw['regular_move_boxes'] ?? 1));
        $halfTurnEnabled = (bool) ($raw['half_turn_enabled'] ?? false);
        $halfTurnGame = $halfTurnEnabled ? (int) ($raw['half_turn_game'] ?? 0) : null;
        $halfTurnMoveBoxes = $halfTurnEnabled ? max(0, (int) ($raw['half_turn_move_boxes'] ?? 0)) : null;
        $directionRaw = (string) ($raw['direction'] ?? 'right');
        $direction = in_array($directionRaw, ['right', 'left'], true) ? $directionRaw : 'right';
        $wrap = array_key_exists('wrap', $raw) ? (bool) $raw['wrap'] : true;

        $settings = [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'lane_from' => $laneFrom,
            'lane_to' => $laneTo,
            'box_width' => $boxWidth,
            'games' => $games,
            'start_time' => $this->validTime($startTime),
            'regular_move_boxes' => $regularMoveBoxes,
            'half_turn_enabled' => $halfTurnEnabled,
            'half_turn_game' => $halfTurnGame,
            'half_turn_move_boxes' => $halfTurnMoveBoxes,
            'direction' => $direction,
            'wrap' => $wrap,
            'second_day_enabled' => (bool) ($raw['second_day_enabled'] ?? false),
            'day1_label' => $raw['day1_label'] ?? null,
        ];

        $settings['day_blocks'] = $this->normalizeDayBlocks($raw, $settings);

        return $settings;
    }

    private function normalizeDayBlocks(array $raw, array $settings): array
    {
        $blocks = [];
        $rawBlocks = $raw['day_blocks'] ?? null;

        if (! is_array($rawBlocks) || count($rawBlocks) < 1) {
            return [];
        }

        foreach ($rawBlocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $gameFrom = max(1, (int) ($block['game_from'] ?? 1));
            $blockGames = max(1, (int) ($block['games'] ?? (($block['game_to'] ?? $gameFrom) - $gameFrom + 1)));
            $gameTo = (int) ($block['game_to'] ?? ($gameFrom + $blockGames - 1));
            $blockGames = max(1, $gameTo - $gameFrom + 1);
            $directionRaw = (string) ($block['direction'] ?? $settings['direction'] ?? 'right');

            $blocks[] = [
                'key' => (string) ($block['key'] ?? ('day' . ($index + 1))),
                'label' => trim((string) ($block['label'] ?? (($index + 1) . '日目'))),
                'game_from' => $gameFrom,
                'game_to' => $gameTo,
                'games' => $blockGames,
                'start_time' => $this->validTime($block['start_time'] ?? null),
                'start_move_boxes' => max(0, (int) ($block['start_move_boxes'] ?? 0)),
                'regular_move_boxes' => max(0, (int) ($block['regular_move_boxes'] ?? $settings['regular_move_boxes'] ?? 1)),
                'half_turn_enabled' => (bool) ($block['half_turn_enabled'] ?? false),
                'half_turn_game' => isset($block['half_turn_game']) ? (int) $block['half_turn_game'] : null,
                'half_turn_move_boxes' => isset($block['half_turn_move_boxes']) ? max(0, (int) $block['half_turn_move_boxes']) : null,
                'direction' => in_array($directionRaw, ['right', 'left'], true) ? $directionRaw : 'right',
                'wrap' => array_key_exists('wrap', $block) ? (bool) $block['wrap'] : (bool) ($settings['wrap'] ?? true),
            ];
        }

        return $blocks;
    }

    private function boxes(array $settings): array
    {
        $boxes = [];

        for ($lane = (int) $settings['lane_from']; $lane <= (int) $settings['lane_to']; $lane += (int) $settings['box_width']) {
            $box = [];
            for ($offset = 0; $offset < (int) $settings['box_width']; $offset++) {
                $box[] = $lane + $offset;
            }
            $boxes[] = $box;
        }

        return $boxes;
    }

    private function boxIndexForLane(int $lane, array $settings): int
    {
        return intdiv($lane - (int) $settings['lane_from'], (int) $settings['box_width']);
    }

    private function gameLanes(int $startBoxIndex, array $settings, array $boxes): array
    {
        $block = [
            'game_from' => 1,
            'game_to' => (int) $settings['games'],
            'games' => (int) $settings['games'],
            'start_move_boxes' => 0,
            'regular_move_boxes' => (int) $settings['regular_move_boxes'],
            'half_turn_enabled' => (bool) $settings['half_turn_enabled'],
            'half_turn_game' => $settings['half_turn_game'],
            'half_turn_move_boxes' => $settings['half_turn_move_boxes'],
            'direction' => $settings['direction'],
            'wrap' => (bool) $settings['wrap'],
        ];

        return $this->gameLanesForBlock($startBoxIndex, $block, $settings, $boxes);
    }

    private function gameLanesForBlock(int $startBoxIndex, array $block, array $settings, array $boxes): array
    {
        $result = [];
        $boxCount = count($boxes);

        if ($boxCount < 1) {
            return $result;
        }

        $direction = ($block['direction'] ?? 'right') === 'left' ? -1 : 1;
        $currentIndex = $startBoxIndex + ((int) ($block['start_move_boxes'] ?? 0) * $direction);
        $currentIndex = $this->normalizeBoxIndex($currentIndex, $boxCount, (bool) ($block['wrap'] ?? $settings['wrap'] ?? true));

        $gameFrom = (int) ($block['game_from'] ?? 1);
        $gameTo = (int) ($block['game_to'] ?? ($gameFrom + (int) ($block['games'] ?? 1) - 1));

        for ($game = $gameFrom; $game <= $gameTo; $game++) {
            if ($game > $gameFrom) {
                $moveBoxes = (int) ($block['regular_move_boxes'] ?? $settings['regular_move_boxes'] ?? 1);

                if (
                    (bool) ($block['half_turn_enabled'] ?? false)
                    && (int) ($block['half_turn_game'] ?? 0) === $game
                    && array_key_exists('half_turn_move_boxes', $block)
                    && $block['half_turn_move_boxes'] !== null
                ) {
                    $moveBoxes = (int) $block['half_turn_move_boxes'];
                }

                $currentIndex += ($moveBoxes * $direction);
                $currentIndex = $this->normalizeBoxIndex($currentIndex, $boxCount, (bool) ($block['wrap'] ?? $settings['wrap'] ?? true));
            }

            $result[$game] = $this->boxLabel($boxes[$currentIndex] ?? []);
        }

        return $result;
    }

    private function normalizeBoxIndex(int $index, int $boxCount, bool $wrap): int
    {
        if ($boxCount < 1) {
            return 0;
        }

        if ($wrap) {
            return (($index % $boxCount) + $boxCount) % $boxCount;
        }

        return max(0, min($boxCount - 1, $index));
    }

    private function boxLabel(array $lanes): string
    {
        return collect($lanes)
            ->map(fn ($lane) => $lane . 'L')
            ->implode('･');
    }

    private function validTime($value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;
    }

    private function licenseTail(?string $licenseNo): string
    {
        $value = trim((string) $licenseNo);

        if ($value === 'アマ') {
            return 'アマ';
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, -4), 4, '0', STR_PAD_LEFT);
    }
}
