<?php

namespace App\Http\Controllers;

use App\Models\Information;
use App\Models\InformationFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InformationController extends Controller
{
    /** 一般公開 */
    public function index(Request $request)
    {
        $year = $request->input('year');
        if ($year === null) { $year = now()->year; } // デフォルト：今年

        $infos = Information::active()->public()
            // 一般公開では、添付も visibility=public のみカウント
            ->withCount(['files' => function ($q) {
                $q->where('visibility', 'public');
            }])
            ->when($year, function ($q) use ($year) {
                // updated_at の年でフィルタ。なければ starts_at/created_at を使っても良いが設計統一のため updated_at で。
                $q->whereYear('updated_at', $year);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20) // 1ページ20件（好みで変更）
            ->withQueryString();

        $availableYears = $this->years();

        return view('informations.index', compact('infos','availableYears'));
    }

    /** 会員向け（要ログイン） */
    public function member(Request $request)
    {
        $user = $request->user();
        $year = $request->input('year');
        if ($year === null) { $year = now()->year; }

        $infos = Information::active()->forUser($user)
            // 会員向けでは public / members どちらもカウント（運用上は visibility で出し分け可能）
            ->withCount('files')
            ->when($year, fn($q) => $q->whereYear('updated_at', $year))
            ->orderByDesc('updated_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $availableYears = $this->years();

        return view('informations.member', compact('infos','availableYears'));
    }

    /**
     * お知らせ 詳細（一般公開 / 会員どちらのルートから来ても同じ画面）
     * - /info/{information}            → 一般公開として扱う（添付も public のみ）
     * - /member/info/{information}     → 会員向けとして扱う（添付 public + members）
     */
    public function show(Request $request, Information $information)
    {
        $isMemberMode = $this->isMemberMode($request);

        // アクセス可否を“一覧と同じ条件”で保証する（URL直打ち対策）
        if ($isMemberMode) {
            $user = $request->user();
            $ok = Information::query()
                ->active()
                ->forUser($user)
                ->whereKey($information->id)
                ->exists();

            if (!$ok) abort(404);
        } else {
            $ok = Information::query()
                ->active()
                ->public()
                ->whereKey($information->id)
                ->exists();

            if (!$ok) abort(404);
        }

        // 添付（表示順）
        $filesQ = $information->files()
            ->orderBy('sort_order')
            ->orderBy('id');

        if (!$isMemberMode) {
            $filesQ->where('visibility', 'public');
        }

        $files = $filesQ->get();

        return view('informations.show', [
            'information' => $information,
            'files' => $files,
            'mode' => $isMemberMode ? 'member' : 'public',
        ]);
    }

    /**
     * 添付ファイルDL
     * - /info/files/{informationFile}          → public のみ
     * - /member/info/files/{informationFile}   → public + members
     */
    public function downloadFile(Request $request, InformationFile $informationFile)
    {
        $isMemberMode = $this->isMemberMode($request);

        $information = $informationFile->information()->first();
        if (!$information) abort(404);

        // ルートに応じたアクセス制御（show と同じ思想）
        if ($isMemberMode) {
            $user = $request->user();
            $ok = Information::query()
                ->active()
                ->forUser($user)
                ->whereKey($information->id)
                ->exists();

            if (!$ok) abort(404);
        } else {
            // 一般公開モードでは、ファイル自体も public 以外は落とさせない
            if (($informationFile->visibility ?? 'public') !== 'public') abort(404);

            $ok = Information::query()
                ->active()
                ->public()
                ->whereKey($information->id)
                ->exists();

            if (!$ok) abort(404);
        }

        // file_path は「storage/〜」「public/〜」など混在し得るので正規化
        $path = (string)($informationFile->file_path ?? '');
        $path = trim($path);
        $path = preg_replace('#^/?storage/#', '', $path);
        $path = preg_replace('#^/?public/#', '', $path);
        $path = ltrim($path, '/');

        // 原則: storage/app/public 配下（disk: public）
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        // DL時のファイル名
        $downloadName = (string)($informationFile->title ?? '');
        $downloadName = trim($downloadName);

        if ($downloadName === '') {
            $downloadName = basename($path);
        } else {
            // 拡張子が無ければ path から補完
            if (!str_contains($downloadName, '.')) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                if ($ext) $downloadName .= '.' . $ext;
            }
        }

        return Storage::disk('public')->download($path, $downloadName);
    }

    /** 情報が存在する年一覧（降順） */
    private function years(): array
    {
        return DB::table('informations')
            ->selectRaw("DISTINCT EXTRACT(YEAR FROM COALESCE(updated_at, starts_at, created_at))::int AS y")
            ->orderByDesc('y')
            ->pluck('y')
            ->all();
    }

    /** /member/info 配下かどうか（ルート名依存を避け、URLで判定） */
    private function isMemberMode(Request $request): bool
    {
        $path = ltrim((string)$request->path(), '/');
        return str_starts_with($path, 'member/info');
    }
}
