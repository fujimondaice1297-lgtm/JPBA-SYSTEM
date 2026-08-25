<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovedBall;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApprovedBallController extends Controller
{
    public function filter(Request $request)
    {
        $validated = $request->validate([
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'release_year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) now()->year + 1)],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $manufacturer = trim((string) ($validated['manufacturer'] ?? ''));
        $releaseYear = (int) ($validated['release_year'] ?? 0);
        $name = trim((string) ($validated['name'] ?? ''));

        $query = ApprovedBall::query()->where('approved', true);

        if ($manufacturer !== '') {
            $query->whereRaw('lower(manufacturer) = ?', [Str::lower($manufacturer)]);
        }

        if ($releaseYear > 0) {
            $query->whereYear('release_date', $releaseYear);
        }

        if ($name !== '') {
            $keyword = '%'.Str::lower($name).'%';
            $query->where(function ($scope) use ($keyword) {
                $scope->whereRaw('lower(name) like ?', [$keyword])
                    ->orWhereRaw('lower(coalesce(name_kana, \'\')) like ?', [$keyword])
                    ->orWhereRaw('lower(coalesce(brand, \'\')) like ?', [$keyword]);
            });
        }

        return response()->json(
            $query
                ->select(['id', 'name', 'name_kana', 'manufacturer', 'brand', 'release_date'])
                ->orderBy('manufacturer')
                ->orderBy('sort_name')
                ->orderBy('name')
                ->get()
                ->map(fn (ApprovedBall $ball) => [
                    'id' => $ball->id,
                    'name' => $ball->name,
                    'name_kana' => $ball->name_kana,
                    'manufacturer' => $ball->manufacturer,
                    'brand' => $ball->brand,
                    'release_date' => $ball->release_date?->format('Y-m-d'),
                    'release_year' => $ball->release_year,
                ])
                ->values()
        );
    }
}
