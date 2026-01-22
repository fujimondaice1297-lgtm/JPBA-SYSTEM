<?php

namespace App\Http\Controllers;

use App\Models\ApprovedBall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ApprovedBallImportController extends Controller
{
    public function showImportForm()
    {
        return view('approved_balls.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $file = $request->file('csv_file');
        $h = fopen($file->getRealPath(), 'r');
        if (!$h) return back()->with('error', 'CSVを開けませんでした');

        fgetcsv($h); // ヘッダ読み飛ばし（信用しない。位置で読む）

        $toUtf8 = fn($v) => $v !== null ? mb_convert_encoding($v, 'UTF-8', 'SJIS-win,UTF-8') : null;

        $parseDate = function (?string $s): ?string {
            if ($s === null || trim($s) === '') return null;
            $s = trim($s);
            // よくあるパターンを丁寧に試す（YYYY/M/D, YYYY-MM-DD, YYYY年M月D日 など）
            $cands = ['Y-n-j','Y-m-d','Y/m/d','Y.n.j','Ynj','Y年n月j日'];
            foreach ($cands as $fmt) {
                $dt = Carbon::createFromFormat($fmt, $s);
                if ($dt !== false) return $dt->format('Y-m-d');
            }
            // 4桁年しかない等 → 1月1日に寄せる
            if (preg_match('/\b(19\d{2}|20\d{2})\b/u', $s, $m)) {
                return "{$m[1]}-01-01";
            }
            // Carbonに投げてワンチャン
            try { return Carbon::parse($s)->format('Y-m-d'); } catch (\Throwable $e) {}
            return null;
        };

        $parseApproved = function (?string $s): bool {
            if ($s === null) return false;
            $s = mb_strtolower(trim($s), 'UTF-8');
            return in_array($s, ['○','〇','1','true','t','y','yes','on'], true) || $s === '○' || $s === '〇';
        };

        $imported = 0;

        \DB::beginTransaction();
        try {
            while (($row = fgetcsv($h)) !== false) {
                $row = array_map($toUtf8, $row);
                if (!array_filter($row, fn($v) => trim((string)$v) !== '')) continue;

                // A:id（必須・数値）
                $idRaw = $row[0] ?? null;
                if ($idRaw === null || !ctype_digit((string)$idRaw)) continue;
                $id = (int)$idRaw;

                $manufacturer = trim((string)($row[1] ?? ''));
                $name         = trim((string)($row[2] ?? ''));
                $dateStr      = $row[3] ?? null; // D列
                $nameKana     = trim((string)($row[4] ?? ''));
                $approved     = $parseApproved($row[5] ?? null);

                $releaseDate  = $parseDate($dateStr);

                if ($manufacturer === '' && $name === '') continue;

                \App\Models\ApprovedBall::updateOrCreate(
                    ['id' => $id],
                    [
                        'manufacturer' => $manufacturer ?: null,
                        'name'         => $name ?: null,
                        'name_kana'    => $nameKana ?: null,
                        'approved'     => $approved,
                        'release_date' => $releaseDate, // ← 年ではなく日付
                    ]
                );
                $imported++;
            }
            fclose($h);

            // シーケンス追従（次のINSERTで重複しないように）
            \DB::statement("
                SELECT setval(
                    pg_get_serial_sequence('approved_balls','id'),
                    COALESCE((SELECT MAX(id) FROM approved_balls), 1)
                )
            ");

            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            if (is_resource($h)) fclose($h);
            return back()->with('error', '取り込み失敗: ' . $e->getMessage());
        }

        return redirect()->route('approved_balls.index')->with('success', "{$imported} 件インポートしました");
    }

}
