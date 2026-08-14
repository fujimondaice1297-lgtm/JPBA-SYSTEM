<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Services\ProBowlerPhotoService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicPlayerPhotoController extends Controller
{
    public function show(ProBowler $bowler, ProBowlerPhotoService $photos): BinaryFileResponse
    {
        $mayViewHidden = auth()->check() && (
            auth()->user()?->isAdmin()
            || auth()->user()?->pro_bowler_id === $bowler->id
        );
        abort_unless((bool) $bowler->is_visible || $mayViewHidden, 404);

        $relative = $photos->relativeStoragePath($bowler->public_image_path);
        abort_unless($relative !== null && Storage::disk('public')->exists($relative), 404);

        $absolute = Storage::disk('public')->path($relative);
        $mime = mime_content_type($absolute) ?: 'application/octet-stream';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
