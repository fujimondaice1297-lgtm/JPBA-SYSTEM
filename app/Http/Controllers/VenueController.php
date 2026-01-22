<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    // 検索API（JSON）
    public function search(Request $request)
    {
        // 用語：JSON（ブラウザやアプリがやり取りする軽量なデータ形式）
        $q = trim((string)$request->query('q', ''));
        $items = Venue::query()
            ->when($q !== '', function ($qbuilder) use ($q) {
                $qbuilder->where('name', 'ILIKE', '%'.$q.'%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id','name','address','tel','fax','website_url']);

        return response()->json($items);
    }

    // 1件取得（id指定）
    public function show($id)
    {
        $v = Venue::findOrFail($id, ['id','name','address','tel','fax','website_url']);
        return response()->json($v);
    }
}
