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

        $slotByLane = [];
        $rows = [];

        foreach ($entries as $entry) {
            $lane = (int) $entry->lane;

            if ($lane < (int) $settings['lane_from'] || $lane > (int) $settings['lane_to']) {
                continue;
            }

            $slotByLane[$lane] = ($slotByLane[$lane] ?? 0) + 1;
            $slotNo = $slotByLane[$lane];

            $startBoxIndex = $this->boxIndexForLane($lane, $settings);

            $rows[] = [
                'entry' => $entry,
                'bowler' => $entry->bowler,
                'start_lane_label' => $lane . 'L-' . $slotNo,
                'license_tail' => $this->licenseTail($entry->bowler->license_no ?? null),
                'kibetsu' => $entry->bowler->kibetsu ?? null,
                'game_lanes' => $this->gameLanes($startBoxIndex, $settings, $boxes),
            ];
        }

        return [
            'settings' => $settings,
            'boxes' => $boxes,
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
        $direction = in_array(($raw['direction'] ?? 'right'), ['right', 'left'], true) ? $raw['direction'] : 'right';

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'lane_from' => $laneFrom,
            'lane_to' => $laneTo,
            'box_width' => $boxWidth,
            'games' => $games,
            'start_time' => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime) ? $startTime : null,
            'regular_move_boxes' => $regularMoveBoxes,
            'half_turn_enabled' => $halfTurnEnabled,
            'half_turn_game' => $halfTurnGame,
            'half_turn_move_boxes' => $halfTurnMoveBoxes,
            'direction' => $direction,
            'wrap' => array_key_exists('wrap', $raw) ? (bool) $raw['wrap'] : true,
        ];
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
        $result = [];
        $boxCount = count($boxes);
        $currentIndex = $startBoxIndex;

        for ($game = 1; $game <= (int) $settings['games']; $game++) {
            if ($game > 1) {
                $moveBoxes = (int) $settings['regular_move_boxes'];

                if (
                    (bool) $settings['half_turn_enabled']
                    && (int) $settings['half_turn_game'] === $game
                    && $settings['half_turn_move_boxes'] !== null
                ) {
                    $moveBoxes = (int) $settings['half_turn_move_boxes'];
                }

                $direction = $settings['direction'] === 'left' ? -1 : 1;
                $currentIndex += ($moveBoxes * $direction);

                if ((bool) $settings['wrap']) {
                    $currentIndex = (($currentIndex % $boxCount) + $boxCount) % $boxCount;
                } else {
                    $currentIndex = max(0, min($boxCount - 1, $currentIndex));
                }
            }

            $result[$game] = $this->boxLabel($boxes[$currentIndex] ?? []);
        }

        return $result;
    }

    private function boxLabel(array $lanes): string
    {
        return collect($lanes)
            ->map(fn ($lane) => $lane . 'L')
            ->implode('･');
    }

    private function licenseTail(?string $licenseNo): string
    {
        $digits = preg_replace('/\D+/', '', (string) $licenseNo);

        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, -4), 4, '0', STR_PAD_LEFT);
    }
}
