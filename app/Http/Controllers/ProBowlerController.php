<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProBowler;
use App\Models\District;
use App\Models\Instructor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProBowlerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /* =========================
       一般: 一覧
    ========================== */
    public function index(Request $request)
    {
        $titleYear = $request->input('title_year');

        $titleFrom = $request->input('title_from');
        $titleTo   = $request->input('title_to');

        // ▼一覧表示用クエリ
        $query = ProBowler::query()
            ->with('district')
            ->withCount('titles')
            ->withCount([
                'titles as titles_count_year' => function ($q) use ($titleYear) {
                    if ($titleYear) $q->where('year', $titleYear);
                },
            ]);

        if ($titleFrom && $titleTo) {
            $query->withCount([
                'titles as titles_count_range' => fn ($q) => $q->whereBetween('year', [$titleFrom, $titleTo]),
            ]);
        }

        // ▼退会者の扱い（デフォルトは除外。include_retired=1 で表示）
        $includeRetired = $request->boolean("include_retired");

        // ▼会員区分マスタ（検索フォーム用。退会系はデフォルト除外）
        $kaiinStatusesQuery = DB::table("kaiin_status")
            ->select("name", "is_retired")
            ->orderBy("id");

        if (!$includeRetired) {
            $kaiinStatusesQuery->where(function ($q) {
                $q->where("is_retired", false)->orWhereNull("is_retired");
            });
        }

        $kaiinStatuses = $kaiinStatusesQuery->get();

        // ▼退会系（一覧のデフォルト除外）
        $retiredNames = DB::table("kaiin_status")->where("is_retired", true)->pluck("name")->all();
        if (!$includeRetired && !empty($retiredNames)) {
            $query->where(function ($q) use ($retiredNames) {
                $q->whereNull("membership_type")
                    ->orWhereNotIn("membership_type", $retiredNames);
            });
        }

        if ($request->filled('license_no'))  $query->where('license_no', 'like', "%{$request->license_no}%");
        if ($request->filled('name'))        $query->where('name', 'like', "%{$request->name}%");
        if ($request->filled('district_id')) $query->where('district_id', $request->district_id);

        // ▼性別（男性/女性）
        if ($request->filled('gender')) {
            $query->where('sex', $request->gender === '男性' ? 1 : 2);
        }

        // ▼プロ入り年（年度）
        if ($request->filled('term_from'))   $query->where('pro_entry_year', '>=', $request->term_from);
        if ($request->filled('term_to'))     $query->where('pro_entry_year', '<=', $request->term_to);

        // ▼会員種別（マスタと一致するもの）
        if ($request->filled("membership_type")) $query->where("membership_type", $request->membership_type);

        if ($request->boolean('has_title'))  $query->has('titles');

        // ページネーション
        $bowlers = $query->orderBy('license_no')->paginate(20)->withQueryString();

        // ▼地区マスタ（labelだけ使う）
        $districts = District::orderBy('id')->get()->pluck('label', 'id');

        return view('pro_bowlers.index', compact('districts', 'kaiinStatuses', 'bowlers'));
    }

    /* =========================
       一般: 編集
    ========================== */
    public function edit(ProBowler $bowler)
    {
        $user = auth()->user();

        // 一般ユーザーは自分のみ編集OK（管理者はOK）
        abort_unless(
            $user && ($user->isAdmin() || $user->pro_bowler_id === $bowler->id),
            403,
            '権限がありません。'
        );

        // ▼管理者/本人でルールと画面を分岐
        if ($user->isAdmin()) {
            return $this->editAdmin($bowler);
        }

        return $this->editSelf($bowler);
    }

    /* =========================
       管理者: 編集画面
    ========================== */
    private function editAdmin(ProBowler $bowler)
    {
        $districts = District::orderBy('id')->get();

        // インストラクター情報（無ければ new）
        $instructor = $bowler->instructorInfo ?: new Instructor();

        // 会員種別（マスタ）
        $kaiinStatus = DB::table('kaiin_status')
            ->orderBy('id')
            ->get(['name']);

        // 公開フラグの選択肢（既存の is_public 相当）
        $publicOptions = [
            '公開' => 1,
            '非公開' => 0,
        ];

        return view('pro_bowlers.edit_admin', compact(
            'bowler',
            'districts',
            'instructor',
            'kaiinStatus',
            'publicOptions'
        ));
    }

    /* =========================
       一般ユーザー: 編集画面
    ========================== */
    private function editSelf(ProBowler $bowler)
    {
        // 本人の自己入力画面
        return view('pro_bowlers.edit_self', [
            'bowler' => $bowler,
        ]);
    }

    /* =========================
       更新（管理者/本人 共通入口）
    ========================== */
    public function update(Request $request, ProBowler $bowler)
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || $user->pro_bowler_id === $bowler->id), 403, '権限がありません。');

        if ($user->isAdmin()) {
            // 管理者は全項目
            $request->validate($this->adminRules());
            $data = $this->sanitizeAdmin($request);

            DB::transaction(function () use ($bowler, $data, $request) {
                $bowler->update($data);
                $this->syncInstructor($request, $bowler);
            });
        } else {
            return $this->updateSelf($request, $bowler);
        }

        $back = $request->input('return') ?: route('pro_bowlers.list', session('pro_bowlers.last_filters', []));
        return redirect()->to($back)->with('success', '更新完了');
    }

    /* =========================
       一般ユーザーの自己更新
    ========================== */
    public function updateSelf(Request $request, ProBowler $bowler)
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || $user->pro_bowler_id === $bowler->id), 403, '権限がありません。');

        $request->validate($this->playerRules());

        $payload = [];
        foreach ($this->playerEditableKeys() as $k) {
            switch ($k) {
                case 'height_cm':  $payload[$k] = $this->intOrNull($request->input($k), 0, 300); break;
                case 'weight_kg':  $payload[$k] = $this->intOrNull($request->input($k), 0, 500); break;
                case 'birth_date': $payload[$k] = $request->input($k) ?: null; break;
                default:
                    $payload[$k] = $request->input($k);
            }
        }

        $bowler->update($payload);

        $back = $request->input('return') ?: route('member.pro_bowler.edit', $bowler);
        return redirect()->to($back)->with('success', '更新しました');
    }

    /* =========================
       管理者: バリデーション
    ========================== */
    private function adminRules(): array
    {
        return [
            // 基本
            'license_no' => ['required', 'string', 'max:20'],
            'name'       => ['required', 'string', 'max:255'],
            'furigana'   => ['nullable', 'string', 'max:255'],
            'term'       => ['nullable', 'integer', 'min:0', 'max:999'],
            'district_id'=> ['nullable', 'integer'],

            // 会員/公開
            'membership_type' => ['nullable', 'string', 'max:255'],
            'is_public'       => ['nullable', 'boolean'],

            // 個人
            'sex'        => ['nullable', 'integer', 'in:1,2'],
            'birth_date' => ['nullable', 'date'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],

            // プロフィール
            'pro_entry_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'birth_place'    => ['nullable', 'string', 'max:255'],
            'height_cm'      => ['nullable', 'integer', 'min:0', 'max:300'],
            'weight_kg'      => ['nullable', 'integer', 'min:0', 'max:500'],

            // 公開用プロフィール（既存フォームに合わせて必要なもの）
            'bio' => ['nullable', 'string'],
            'sns_facebook' => ['nullable', 'string', 'max:255'],
            'sns_twitter'  => ['nullable', 'string', 'max:255'],
            'sns_instagram'=> ['nullable', 'string', 'max:255'],
            'sns_rankseeker'=> ['nullable', 'string', 'max:255'],

            // インストラクター
            'instructor_flag' => ['nullable', 'boolean'],
            'lesson_center'   => ['nullable', 'string', 'max:255'],
            'lesson_notes'    => ['nullable', 'string'],
            'certifications'  => ['nullable', 'string'],
        ];
    }

    /* =========================
       一般: バリデーション
    ========================== */
    private function playerRules(): array
    {
        // 自己入力で触る項目だけ
        return [
            'birth_date' => ['nullable', 'date'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],

            'birth_place'=> ['nullable', 'string', 'max:255'],
            'height_cm'  => ['nullable', 'integer', 'min:0', 'max:300'],
            'weight_kg'  => ['nullable', 'integer', 'min:0', 'max:500'],

            'bio' => ['nullable', 'string'],
            'sns_facebook' => ['nullable', 'string', 'max:255'],
            'sns_twitter'  => ['nullable', 'string', 'max:255'],
            'sns_instagram'=> ['nullable', 'string', 'max:255'],
            'sns_rankseeker'=> ['nullable', 'string', 'max:255'],
        ];
    }

    private function playerEditableKeys(): array
    {
        return [
            'birth_date',
            'email',
            'phone',
            'birth_place',
            'height_cm',
            'weight_kg',
            'bio',
            'sns_facebook',
            'sns_twitter',
            'sns_instagram',
            'sns_rankseeker',
        ];
    }

    /* =========================
       入力の整形（管理者）
    ========================== */
    private function sanitizeAdmin(Request $request): array
    {
        $data = $request->only([
            'license_no',
            'name',
            'furigana',
            'term',
            'district_id',
            'membership_type',
            'sex',
            'birth_date',
            'email',
            'phone',
            'pro_entry_year',
            'birth_place',
            'bio',
            'sns_facebook',
            'sns_twitter',
            'sns_instagram',
            'sns_rankseeker',
        ]);

        // boolean
        $data['is_public'] = $request->boolean('is_public');

        // 数値系
        $data['term']          = $this->intOrNull($request->input('term'), 0, 999);
        $data['district_id']   = $this->intOrNull($request->input('district_id'), 0, 999999);
        $data['sex']           = $this->intOrNull($request->input('sex'), 1, 2);
        $data['pro_entry_year']= $this->intOrNull($request->input('pro_entry_year'), 1900, 2100);
        $data['height_cm']     = $this->intOrNull($request->input('height_cm'), 0, 300);
        $data['weight_kg']     = $this->intOrNull($request->input('weight_kg'), 0, 500);

        // 空文字→null
        foreach ($data as $k => $v) {
            if (is_string($v) && trim($v) === '') $data[$k] = null;
        }

        return $data;
    }

    private function intOrNull($v, int $min, int $max): ?int
    {
        if ($v === null || $v === '') return null;
        if (!is_numeric($v)) return null;
        $i = (int)$v;
        if ($i < $min || $i > $max) return null;
        return $i;
    }

    /* =========================
       インストラクター同期
    ========================== */
    private function syncInstructor(Request $request, ProBowler $bowler): void
    {
        // テーブルが無い環境でも落ちないように
        if (!Schema::hasTable('pro_bowler_instructor_info')) return;

        $payload = [
            'instructor_flag' => $request->boolean('instructor_flag'),
            'lesson_center'   => $request->input('lesson_center') ?: null,
            'lesson_notes'    => $request->input('lesson_notes') ?: null,
            'certifications'  => $request->input('certifications') ?: null,
        ];

        $bowler->instructorInfo()->updateOrCreate(
            ['pro_bowler_id' => $bowler->id],
            $payload
        );
    }

    /* =========================
       会員: 一覧（高度検索）
    ========================== */
    public function list(Request $request)
    {
        $filters = $request->only([
            'name',
            'license_no',
            'district_id',
            'gender',
            'term_from',
            'term_to',
            'include_inactive',
            'membership_type',
            'title_holder',
            'district_leader',
            'sports_coach_license',
            'title_year',
            'title_from',
            'title_to',
        ]);

        // セッション保存（戻るボタン等に利用）
        session(['pro_bowlers.last_filters' => array_filter($filters, fn($v) => $v !== null && $v !== '')]);

        // ▼一覧表示用クエリ
        $query = ProBowler::query()
            ->with(['district', 'instructorInfo'])
            ->withCount('titles');

        // デフォルトは active のみ
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        // ▼名前
        if ($request->filled('name')) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        // ▼ライセンスNo
        if ($request->filled('license_no')) {
            $query->where('license_no', 'like', "%{$request->license_no}%");
        }

        // ▼地区
        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        // ▼性別
        if ($request->filled('gender')) {
            $query->where('sex', $request->gender === '男性' ? 1 : 2);
        }

        // ▼プロ入り年
        if ($request->filled('term_from')) {
            $query->where('pro_entry_year', '>=', $request->term_from);
        }
        if ($request->filled('term_to')) {
            $query->where('pro_entry_year', '<=', $request->term_to);
        }

        // ▼会員種別（既存列）
        if ($request->filled('membership_type')) {
            $query->where('membership_type', $request->membership_type);
        }

        // ▼タイトル保有者（タイトル数 > 0）
        if ($request->boolean('title_holder')) {
            $query->has('titles');
        }

        // ▼インストラクター（既存のフラグ）
        if ($request->filled('district_leader')) {
            // 将来: is_district_leader カラム追加後に対応
            // いまは instructorInfo などに仮置きするならここ
        }

        if ($request->filled('sports_coach_license')) {
            // 将来: has_sports_coach_license カラム追加後に対応
        }

        // ▼タイトル年/範囲
        $titleYear = $request->input('title_year');
        $titleFrom = $request->input('title_from');
        $titleTo   = $request->input('title_to');

        if ($titleYear) {
            $query->withCount([
                'titles as titles_count_year' => fn ($q) => $q->where('year', $titleYear),
            ]);
        }

        if ($titleFrom && $titleTo) {
            $query->withCount([
                'titles as titles_count_range' => fn ($q) => $q->whereBetween('year', [$titleFrom, $titleTo]),
            ]);
        }

        // 並び
        $sort = $request->input('sort', 'license_no');
        $dir  = $request->input('dir', 'asc');
        if (!in_array($sort, ['license_no', 'name', 'term', 'pro_entry_year', 'titles_count'], true)) {
            $sort = 'license_no';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        $bowlers = $query->orderBy($sort, $dir)->paginate(50)->withQueryString();

        // マスタ
        $districts = District::orderBy('id')->get()->pluck('label', 'id');

        return view('pro_bowlers.list', compact('bowlers', 'districts', 'filters', 'sort', 'dir'));
    }

    /* =========================
       会員: 詳細
    ========================== */
    public function show(ProBowler $bowler)
    {
        $bowler->load(['district', 'instructorInfo', 'titles']);
        return view('pro_bowlers.show', compact('bowler'));
    }

    /* =========================
       管理者: 新規作成フォーム
    ========================== */
    public function create()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $districts = District::orderBy('id')->get();
        $kaiinStatus = DB::table('kaiin_status')->orderBy('id')->get(['name']);

        return view('pro_bowlers.create', compact('districts', 'kaiinStatus'));
    }

    /* =========================
       管理者: 新規作成
    ========================== */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate($this->adminRules());
        $data = $this->sanitizeAdmin($request);

        DB::transaction(function () use ($data, $request) {
            $bowler = ProBowler::create($data);
            $this->syncInstructor($request, $bowler);
        });

        return redirect()->route('pro_bowlers.list')->with('success', '登録しました');
    }

    /* =========================
       管理者: 削除
    ========================== */
    public function destroy(ProBowler $bowler)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        DB::transaction(function () use ($bowler) {
            // 関連がある場合は必要に応じて削除/無効化
            if (Schema::hasTable('pro_bowler_instructor_info')) {
                $bowler->instructorInfo()->delete();
            }
            $bowler->delete();
        });

        return redirect()->route('pro_bowlers.list')->with('success', '削除しました');
    }
}
