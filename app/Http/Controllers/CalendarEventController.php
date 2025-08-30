<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarEventController extends Controller
{
    /** 一覧（最新日付から） */
    public function index()
    {
        $events = CalendarEvent::orderByDesc('start_date')->paginate(20);
        return view('calendar_events.index', compact('events'));
    }

    /** 新規作成フォーム */
    public function create()
    {
        $e = new CalendarEvent(['kind' => 'other']);
        return view('calendar_events.create', compact('e'));
    }

    /** 登録（保存後はその月の月間カレンダーへ：現仕様を維持） */
    public function store(Request $r)
    {
        $data = $this->validateData($r);

        // 同名 × 同開始日の重複を事前ブロック（DBユニーク張ってなくても安全）
        $exists = CalendarEvent::where('title', $data['title'])
            ->whereDate('start_date', $data['start_date'])
            ->exists();
        if ($exists) {
            return back()
                ->withErrors(['title' => '同名 × 同開始日のイベントが既に存在します'])
                ->withInput();
        }

        $ev = CalendarEvent::create($data);
        [$yy, $mm] = $this->ym($ev->start_date);
        return redirect()->route('calendar.monthly', [$yy, $mm])
            ->with('status', 'カレンダーに追加しました');
    }

    /** 編集フォーム */
    public function edit(CalendarEvent $event)
    {
        return view('calendar_events.edit', ['e' => $event]);
    }

    /** 更新（保存後はその月の月間カレンダーへ） */
    public function update(Request $r, CalendarEvent $event)
    {
        $data = $this->validateData($r);

        $exists = CalendarEvent::where('title', $data['title'])
            ->whereDate('start_date', $data['start_date'])
            ->where('id', '<>', $event->id)
            ->exists();
        if ($exists) {
            return back()
                ->withErrors(['title' => '同名 × 同開始日のイベントが既に存在します'])
                ->withInput();
        }

        $event->update($data);
        [$yy, $mm] = $this->ym($event->start_date);
        return redirect()->route('calendar.monthly', [$yy, $mm])
            ->with('status', '更新しました');
    }

    /** 削除（一覧へ戻す。必要なら直前月へ飛ばす実装にも変えられる） */
    public function destroy(CalendarEvent $event)
    {
        $event->delete();
        return back()->with('status', '削除しました');
    }

    /* ===================== CSV/TSV インポート ===================== */

    /** インポート画面 */
    public function importForm()
    {
        return view('calendar_events.import');
    }

    /** インポート処理 */
    public function import(Request $r)
    {
        $r->validate([
            'file'      => ['required', 'file', 'mimes:csv,txt'],
            'delimiter' => ['nullable', 'in:,;\t'],
        ]);
        $delimiter = $r->input('delimiter', ',');
        if ($delimiter === '\\t') { $delimiter = "\t"; }

        $path = $r->file('file')->getRealPath();
        $fh = fopen($path, 'r');
        if (!$fh) {
            return back()->withErrors(['file' => 'ファイルを開けませんでした']);
        }

        // 1行目ヘッダ
        $headers = fgetcsv($fh, 0, $delimiter);
        if (!$headers) {
            return back()->withErrors(['file' => 'ヘッダ行がありません（title,start_date,end_date,venue,kind）']);
        }
        $required = ['title','start_date','end_date','venue','kind'];
        $idx = array_flip($headers);
        foreach ($required as $col) {
            if (!array_key_exists($col, $idx)) {
                return back()->withErrors(['file' => "必須列 {$col} が見つかりません"]);
            }
        }

        $inserted = 0; $skipped = 0; $errors = [];
        DB::beginTransaction();
        try {
            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                if (count($row) === 1 && trim($row[0]) === '') { continue; }

                $data = [
                    'title'      => $row[$idx['title']] ?? '',
                    'start_date' => $row[$idx['start_date']] ?? '',
                    'end_date'   => $row[$idx['end_date']]   ?: ($row[$idx['start_date']] ?? ''),
                    'venue'      => $row[$idx['venue']] ?? null,
                    'kind'       => $row[$idx['kind']]  ?? 'other',
                ];

                $v = validator($data, [
                    'title'      => ['required','string','max:255'],
                    'start_date' => ['required','date'],
                    'end_date'   => ['required','date','after_or_equal:start_date'],
                    'venue'      => ['nullable','string','max:255'],
                    'kind'       => ['required','in:pro_test,approved,other'],
                ]);
                if ($v->fails()) {
                    $errors[] = '行エラー: '.implode(', ', $v->errors()->all());
                    $skipped++;
                    continue;
                }

                // 同名×同開始日ならスキップ
                $dup = CalendarEvent::where('title', $data['title'])
                    ->whereDate('start_date', $data['start_date'])
                    ->exists();
                if ($dup) { $skipped++; continue; }

                CalendarEvent::create($data);
                $inserted++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($fh);
            return back()->withErrors(['file' => '取り込み中に例外: '.$e->getMessage()]);
        }
        fclose($fh);

        $msg = "取り込み完了: 追加 {$inserted} 件 / スキップ {$skipped} 件";
        if ($errors) { $msg .= '（一部エラーあり：'.count($errors).'件）'; }

        return back()->with('status', $msg)->with('import_errors', $errors);
    }

    /* ===================== ヘルパ ===================== */

    /** 共通バリデーション */
    private function validateData(Request $r): array
    {
        return $r->validate([
            'title'      => ['required','string','max:255'],
            'start_date' => ['required','date'],
            'end_date'   => ['required','date','after_or_equal:start_date'],
            'venue'      => ['nullable','string','max:255'],
            'kind'       => ['required','in:pro_test,approved,other'],
        ]);
    }

    /** Carbon/文字列どちらでも年・月を抽出（route用） */
    private function ym($date): array
    {
        $ts = is_string($date) ? strtotime($date) : strtotime((string)$date);
        return [ (int)date('Y', $ts), (int)date('n', $ts) ];
    }
}
