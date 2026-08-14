@php
    $lookup = (array) ($scoreEntryBallLookup ?? []);
    $playerName = trim((string) ($displayName ?? '')) ?: '—';
    $resolvedProBowlerId = is_numeric($proBowlerId ?? null) ? (int) $proBowlerId : 0;
    $normalizedLicense = strtoupper(
        preg_replace('/\s+/u', '', trim((string) ($licenseNo ?? ''))) ?? ''
    );
    $licenseDigits = preg_replace('/\D+/u', '', $normalizedLicense) ?: '';
    $licenseTail = $licenseDigits !== '' ? substr($licenseDigits, -4) : '';

    $entryBall = null;
    if ($resolvedProBowlerId > 0) {
        $entryBall = $lookup['pro:' . $resolvedProBowlerId] ?? null;
    }
    if (!$entryBall && $normalizedLicense !== '') {
        $entryBall = $lookup['license:' . $normalizedLicense] ?? null;
    }
    if (!$entryBall && $licenseTail !== '') {
        $entryBall = $lookup['tail:' . $licenseTail] ?? null;
    }

    $ballCount = (int) ($entryBall['ball_count'] ?? 0);
@endphp

@if($entryBall)
    <a
        href="{{ route('scores.entry_balls.show', [
            'entry' => $entryBall['entry_id'],
            'return' => request()->fullUrl(),
            'public' => (int) request('public', 0),
        ]) }}"
        title="大会登録ボールを確認（{{ $ballCount }}個）"
        aria-label="{{ $playerName }}の大会登録ボールを確認（{{ $ballCount }}個）"
        style="color:inherit; font-weight:inherit; text-decoration:underline; text-decoration-style:dotted; text-underline-offset:3px;"
    >{{ $playerName }}</a>
    <span
        style="display:inline-block; margin-left:.35rem; padding:.08rem .38rem; border-radius:999px; background:{{ $ballCount > 0 ? '#e8f7ee' : '#f1f3f5' }}; color:{{ $ballCount > 0 ? '#157347' : '#6c757d' }}; font-size:.68rem; font-weight:700; white-space:nowrap;"
    >ボール {{ $ballCount }}</span>
@else
    {{ $playerName }}
@endif
