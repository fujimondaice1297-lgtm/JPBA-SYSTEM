<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TournamentPhotoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tournament_id'  => ['required', 'integer'],
            'participant_key'=> ['required', 'string', 'max:255'],
            'display_name'   => ['nullable', 'string', 'max:255'],
            'photo'          => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $tournamentId = (int) $data['tournament_id'];
        $participantKey = trim((string) $data['participant_key']);
        $displayName = trim((string) ($data['display_name'] ?? ''));

        $baseName = $participantKey !== '' ? $participantKey : $displayName;
        $safeBaseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName ?? '');
        $safeBaseName = trim((string) $safeBaseName, '_');

        if ($safeBaseName === '') {
            return back()->withErrors([
                'photo' => '写真保存用の識別子が作れませんでした。',
            ]);
        }

        $targetDir = public_path('tournament_images/' . $tournamentId);

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        foreach (glob($targetDir . DIRECTORY_SEPARATOR . $safeBaseName . '.*') ?: [] as $oldFile) {
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        $uploaded = $request->file('photo');
        $ext = strtolower((string) ($uploaded?->getClientOriginalExtension() ?: $uploaded?->extension() ?: 'jpg'));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $fileName = $safeBaseName . '.' . $ext;
        $uploaded->move($targetDir, $fileName);

        return back()->with('success', '大会専用写真を登録しました。');
    }
}