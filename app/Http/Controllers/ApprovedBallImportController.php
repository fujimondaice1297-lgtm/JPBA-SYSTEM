<?php

namespace App\Http\Controllers;

use App\Models\ApprovedBall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApprovedBallImportController extends Controller
{
    public function showImportForm()
    {
        return view('approved_balls.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle); // 1行目（ヘッダー）をスキップ

        $imported = 0;
        while (($data = fgetcsv($handle)) !== false) {
            // カラム順：name, manufacturer, release_year
            ApprovedBall::create([
                'name' => $data[0],
                'manufacturer' => $data[1],
                'release_year' => $data[2] ?? null,
            ]);
            $imported++;
        }

        fclose($handle);

        return redirect()->route('approved_balls.index')
            ->with('success', "{$imported} 件のボールをインポートしました！");
    }
}
