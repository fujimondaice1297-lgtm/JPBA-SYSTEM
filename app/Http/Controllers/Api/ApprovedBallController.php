<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ApprovedBall;
use Illuminate\Support\Facades\DB; // ← これを追加！

class ApprovedBallController extends Controller
{
    public function filter(Request $request)
    {
        $manufacturer = $request->input('manufacturer');
        $releaseYear = $request->input('release_year');

        $query = ApprovedBall::query();

        if ($manufacturer) {
            $query->where(DB::raw('LOWER(manufacturer)'), strtolower($manufacturer));
        }

        if ($releaseYear) {
            $query->where('release_year', $releaseYear);
        }

        $query->where('approved', true);

        return $query->select('id', 'name', 'manufacturer', 'release_year')->get();
    }
}
