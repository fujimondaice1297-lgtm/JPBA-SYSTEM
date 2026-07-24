@php
    $hasStepLadderBracketImage = isset($stepLadderBracketImage)
        && is_string($stepLadderBracketImage)
        && trim($stepLadderBracketImage) !== '';

    $stepLadderPdfData = isset($stepLadderPdf) && is_array($stepLadderPdf)
        ? $stepLadderPdf
        : [];

    $stepMeta = (array) ($stepLadderPdfData['meta'] ?? []);
    $stepStandings = array_values((array) ($stepLadderPdfData['standings'] ?? []));

    $officialMainTitleSafe = trim((string) ($officialMainTitle ?? ($tournament->name ?? '')));
    $officialVenueTitleSafe = trim((string) ($officialVenueTitle ?? ($venueText ?? '')));
    $stepSeedSnapshotId = trim((string) ($stepMeta['seed_snapshot_id'] ?? ''));
@endphp

@if ($hasStepLadderBracketImage)
    <div style="page-break-before: {{ !empty($suppressInitialDiagramPageBreak) ? 'auto' : 'always' }}; padding: 18px 18px 0 18px; font-family: ipaexg, sans-serif;">
        <div style="text-align: center; margin-bottom: 8px;">
            <div style="font-size: 26px; font-weight: bold; line-height: 1.35;">
                {{ $officialMainTitleSafe }}
            </div>
            <div style="font-size: 18px; font-weight: bold; line-height: 1.35;">
                決勝ステップラダー
                @if ($officialVenueTitleSafe !== '')
                    ／ {{ $officialVenueTitleSafe }}
                @endif
            </div>
            <div style="font-size: 11px; margin-top: 4px;">
                進出元：ラウンドロビン最終成績
                @if ($stepSeedSnapshotId !== '')
                    ／ snapshot #{{ $stepSeedSnapshotId }}
                @endif
            </div>
        </div>

        <div style="width: 100%; text-align: center; margin-top: 6px;">
            <img src="{!! $stepLadderBracketImage !!}" alt="決勝ステップラダー対戦表" style="width: 100%; max-width: 760px; height: auto; display: block; margin: 0 auto;">
        </div>

        @if (!empty($stepStandings))
            <table style="width: 78%; margin: 10px auto 0 auto; border-collapse: collapse; font-size: 10px;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #222; padding: 3px; background: #f0f0f0;">順位</th>
                        <th style="border: 1px solid #222; padding: 3px; background: #f0f0f0;">氏名</th>
                        <th style="border: 1px solid #222; padding: 3px; background: #f0f0f0;">ステップラダー結果</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stepStandings as $standing)
                        @php
                            $standing = (array) $standing;
                            $rank = $standing['rank'] ?? $standing['ranking'] ?? null;
                            $player = (array) ($standing['player'] ?? []);
                            $name = trim((string) (
                                $player['display_name']
                                ?? $standing['display_name']
                                ?? $standing['name']
                                ?? $standing['amateur_name']
                                ?? '-'
                            ));
                            $reason = trim((string) ($standing['reason'] ?? ''));
                        @endphp
                        <tr>
                            <td style="border: 1px solid #222; padding: 3px; text-align: center; width: 55px;">
                                {{ $rank ?: '-' }}
                            </td>
                            <td style="border: 1px solid #222; padding: 3px;">
                                {{ $name !== '' ? $name : '-' }}
                            </td>
                            <td style="border: 1px solid #222; padding: 3px;">
                                {{ $reason !== '' ? $reason : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif
