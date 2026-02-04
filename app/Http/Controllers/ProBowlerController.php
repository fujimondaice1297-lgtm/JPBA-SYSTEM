<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProBowler;
use App\Models\District;
use App\Models\Instructor;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class ProBowlerController extends Controller
{
    /* =========================
       一覧（従来どおり）
    ========================== */
    public function index(Request $request)
    {
        $titleYear = $request->integer('title_year');
        $titleFrom = $request->integer('title_year_from');
        $titleTo   = $request->integer('title_year_to');

        $query = ProBowler::query()
            ->with('district')
            ->withCount('titles')
            ->withCount([
                'records as perfect_count'       => fn ($q) => $q->where('record_type', 'perfect'),
                'records as seven_ten_count'     => fn ($q) => $q->where('record_type', 'seven_ten'),
                'records as eight_hundred_count' => fn ($q) => $q->where('record_type', 'eight_hundred'),
            ]);

        if ($titleYear) {
            $query->withCount([
                'titles as titles_count_'.$titleYear => fn ($q) => $q->where('year', $titleYear),
            ]);
        }
        if ($titleFrom && $titleTo) {
            $query->withCount([
                'titles as titles_count_range' => fn ($q) => $q->whereBetween('year', [$titleFrom, $titleTo]),
            ]);
        }

        if ($request->filled('license_no'))  $query->where('license_no', 'like', '%'.$request->license_no.'%');
        if ($request->filled('name'))        $query->where('name_kanji', 'like', '%'.$request->name.'%');
        if ($request->filled('district'))    $query->whereHas('district', fn ($q) => $q->where('label', $request->district));
        if ($request->filled('gender'))      $query->where('sex', $request->gender === '男性' ? 1 : 2);
        if ($request->filled('term_from'))   $query->where('pro_entry_year', '>=', $request->term_from);
        if ($request->filled('term_to'))     $query->where('pro_entry_year', '<=', $request->term_to);
        if ($request->boolean('has_title'))  $query->has('titles');
        if ($request->boolean('has_sports_coach_license')) {
            $query->where(fn ($q) => $q->where('coach_1_status','有')->orWhere('coach_3_status','有')->orWhere('coach_4_status','有'));
        }

        $bowlers = $query->orderBy('license_no')->paginate(20)->withQueryString();

        $order = ['北海道','東北','北関東','埼玉','千葉','城東','城南','城西','三多摩','神奈川・東','神奈川・西','静岡','甲信越','東海','北陸','関西・東','関西・西','関西・南','中国四国','九州・北','九州･南／沖縄','海外'];
        $districts = District::all()
            ->sortBy(fn($d) => array_search($d->label, $order))
            ->mapWithKeys(fn($d) => [$d->id => $d->label]);

        return view('pro_bowlers.index', compact('districts', 'bowlers'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        $order = ['北海道','東北','北関東','埼玉','千葉','城東','城南','城西','三多摩','神奈川・東','神奈川・西','静岡','甲信越','東海','北陸','関西・東','関西・西','関西・南','中国四国','九州・北','九州･南／沖縄','海外'];

        $districts = District::all()
            ->sortBy(fn($d) => array_search($d->label, $order))
            ->mapWithKeys(fn($d) => [$d->id => $d->label]);

        return view('pro_bowlers.athlete_form', [
            'districts' => $districts,
            'bowler'    => null,
        ]);
    }

    /* =========================
       新規登録（管理者のみ）
    ========================== */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate($this->adminRules());

        // 画像保存（自動で /storage リンク作成 or /uploads に直置き）
        $this->handleUploads($request, null);

        $data = $this->buildAdminPayload($request, $validated);

        if ($data['mailing_addr_same_as_org'] ?? false) {
            $data['mailing_zip']   = $this->nullIfBlank($request->input('organization_zip'));
            $data['mailing_addr1'] = $this->nullIfBlank($request->input('organization_addr1'));
            $data['mailing_addr2'] = $this->nullIfBlank($request->input('organization_addr2'));
        }
        if ($data['public_addr_same_as_org'] ?? false) {
            $data['public_zip']   = $this->nullIfBlank($request->input('organization_zip'));
            $data['public_addr1'] = $this->nullIfBlank($request->input('organization_addr1'));
            $data['public_addr2'] = $this->nullIfBlank($request->input('organization_addr2'));
        }
        if (empty($data['birthdate_public']) && !empty($data['birthdate']) && !($data['birthdate_public_is_private'] ?? false)) {
            $data['birthdate_public'] = $data['birthdate'];
        }

        DB::beginTransaction();
        try {
            $bowler = ProBowler::create($data);
            DB::commit();
            $this->syncInstructor($request, $bowler);
        } catch (QueryException $e) {
            DB::rollBack();
            $isUniqueViolation = (string)$e->getCode() === '23505';
            if ($isUniqueViolation) {
                $bowler = ProBowler::where('license_no', $data['license_no'])->first();
                if ($bowler) {
                    $bowler->update($data);
                    $this->syncInstructor($request, $bowler);
                    $back = $request->input('return') ?: route('pro_bowlers.list', session('pro_bowlers.last_filters', []));
                    return redirect()->to($back)->with('success', 'すでに登録済みのため内容を更新しました');
                }
            }
            throw $e;
        }

        $back = $request->input('return') ?: route('pro_bowlers.list', session('pro_bowlers.last_filters', []));
        return redirect()->to($back)->with('success', '登録完了');
    }

    /* =========================
       編集フォーム
    ========================== */
    public function edit($id)
    {
        $bowler = ProBowler::with([
            'titles' => fn($q) => $q->orderByDesc('won_date')->orderByDesc('year')
        ])->withCount('titles')->findOrFail($id);

        $order = ['北海道','東北','北関東','埼玉','千葉','城東','城南','城西','三多摩','神奈川・東','神奈川・西','静岡','甲信越','東海','北陸','関西・東','関西・西','関西・南','中国四国','九州・北','九州･南／沖縄','海外'];
        $districts = District::all()
            ->sortBy(fn($d) => array_search($d->label, $order))
            ->pluck('label', 'id');

        return view('pro_bowlers.athlete_form', compact('bowler', 'districts'));
    }

    /* =========================
       更新
    ========================== */
    public function update(Request $request, $id)
    {
        $bowler   = ProBowler::findOrFail($id);
        $isAdmin  = auth()->user()?->isAdmin();
        $isSelf   = auth()->user()?->pro_bowler_id === $bowler->id;

        abort_unless($isAdmin || $isSelf, 403, '権限がありません。');

        if ($isAdmin) {
            $validated = $request->validate($this->adminRules());

            // 画像保存（旧ファイルがローカルなら削除）
            $this->handleUploads($request, $bowler);

            $data = $this->buildAdminPayload($request, $validated);

            if ($data['mailing_addr_same_as_org'] ?? false) {
                $data['mailing_zip']   = $this->nullIfBlank($request->input('organization_zip'));
                $data['mailing_addr1'] = $this->nullIfBlank($request->input('organization_addr1'));
                $data['mailing_addr2'] = $this->nullIfBlank($request->input('organization_addr2'));
            }
            if ($data['public_addr_same_as_org'] ?? false) {
                $data['public_zip']   = $this->nullIfBlank($request->input('organization_zip'));
                $data['public_addr1'] = $this->nullIfBlank($request->input('organization_addr1'));
                $data['public_addr2'] = $this->nullIfBlank($request->input('organization_addr2'));
            }
            if (empty($data['birthdate_public']) && !empty($data['birthdate']) && !($data['birthdate_public_is_private'] ?? false)) {
                $data['birthdate_public'] = $data['birthdate'];
            }

            $bowler->update($data);
            $this->syncInstructor($request, $bowler);
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
                case 'weight_kg':  $payload[$k] = $this->intOrNull($request->input($k), 0, 400); break;
                case 'blood_type': $payload[$k] = $this->normalizeBlood($request->input($k)); break;
                case 'height_is_public':
                case 'weight_is_public':
                case 'blood_type_is_public':
                    $payload[$k] = $request->boolean($k); break;
                default:
                    $payload[$k] = $this->nullIfBlank($request->input($k));
            }
        }

        $bowler->update($payload);

        return redirect()->route('athlete.index')->with('success', 'プロフィールを更新しました');
    }

    /* =========================
       検索つき一覧（list）
    ========================== */
    public function list(Request $request)
    {
        // 画面から来る入力（既存のfiltersも壊さない）
        $filters = $request->only([
            'license_no',
            'id_start', 'id_end',          // JPBA式（No.範囲）
            'id_from', 'id_to',            // 旧UI互換（あっても無視しない）
            'pro_entry_year_from', 'pro_entry_year_to',
            'name',
            'district_id', 'district',     // district_id優先、districtは互換
            'gender', 'sex',               // gender優先、sexは互換
            'age_from', 'age_to',
            'titles_from', 'titles_to',
            'has_title',
            'is_district_leader',
            'has_sports_coach_license',
            'instructor_grade',
            'coach_name',
            'include_inactive',            // 退会者も含む
            'sort', 'dir',                 // ★追加：ソート
        ]);

        // ★タイトル数：pro_bowler_titles を集計して JOIN（titles_count を“列として作る”）
        $titlesAgg = DB::table('pro_bowler_titles')
            ->select('pro_bowler_id', DB::raw('count(*) as titles_count'))
            ->groupBy('pro_bowler_id');

        $query = ProBowler::query()
            ->with('district')
            ->leftJoinSub($titlesAgg, 'titles_agg', function ($join) {
                $join->on('pro_bowlers.id', '=', 'titles_agg.pro_bowler_id');
            })
            ->addSelect('pro_bowlers.*')
            ->addSelect(DB::raw('coalesce(titles_agg.titles_count, 0) as titles_count'));

        // 退会者（=非アクティブ）を含めるか
        $includeInactive = (bool)($request->input('include_inactive') ?? false);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        // 氏名（漢字/カナどちらでも部分一致）
        if (!empty($filters['name'])) {
            $name = trim((string)$filters['name']);
            $query->where(function ($q) use ($name) {
                $q->where('name_kanji', 'like', "%{$name}%")
                ->orWhere('name_kana', 'like', "%{$name}%");
            });
        }

        // ライセンスNo（ピンポイントや部分一致用：任意で残す）
        if (!empty($filters['license_no'])) {
            $license = trim((string)$filters['license_no']);
            $query->where('license_no', 'like', "%{$license}%");
        }

        // ====== JPBA式：No.範囲検索（数字） ======
        $start = trim((string)($filters['id_start'] ?? ''));
        $end   = trim((string)($filters['id_end'] ?? ''));

        // 旧UI互換：id_from / id_to が入ってたら fallback
        if ($start === '' && $end === '') {
            $start = trim((string)($filters['id_from'] ?? ''));
            $end   = trim((string)($filters['id_to'] ?? ''));
        }

        if ($start !== '' || $end !== '') {
            $startIsNum = ($start !== '' && ctype_digit($start));
            $endIsNum   = ($end !== '' && ctype_digit($end));

            // 英字No（例: T007）なら完全一致へ
            if (($start !== '' && !$startIsNum) || ($end !== '' && !$endIsNum)) {
                $val = $start !== '' ? $start : $end;
                $query->whereRaw('lower(license_no) = lower(?)', [$val]);
            } else {
                if ($start !== '') $query->where('license_no_num', '>=', (int)$start);
                if ($end !== '')   $query->where('license_no_num', '<=', (int)$end);
            }
        }

        // 地区：district_idを優先（districtは互換）
        if (!empty($filters['district_id'])) {
            $query->where('district_id', (int)$filters['district_id']);
        } elseif (!empty($filters['district'])) {
            $d = $filters['district'];
            if (ctype_digit((string)$d)) {
                $query->where('district_id', (int)$d);
            }
        }

        // 性別：gender（男性/女性）を優先、sex（数値）は互換
        $sexFilter = $filters['sex'] ?? null;

        if (!empty($filters['gender'])) {
            $gender = $filters['gender'];
            if ($gender === '男性') $query->where('sex', 1);
            if ($gender === '女性') $query->where('sex', 2);
        } elseif ($sexFilter !== null && $sexFilter !== '' && in_array((int)$sexFilter, [1, 2], true)) {
            $query->where('sex', (int)$sexFilter);
        }

        // 年齢フィルタ（birthdateがある前提）
        if (!empty($filters['age_from']) || !empty($filters['age_to'])) {
            $today = now();
            if (!empty($filters['age_from'])) {
                $maxBirth = $today->copy()->subYears((int)$filters['age_from'])->endOfDay();
                $query->where('birthdate', '<=', $maxBirth);
            }
            if (!empty($filters['age_to'])) {
                $minBirth = $today->copy()->subYears((int)$filters['age_to'] + 1)->addDay()->startOfDay();
                $query->where('birthdate', '>=', $minBirth);
            }
        }

        // タイトル：★has_title は pro_bowlers.has_title ではなく “実タイトル件数” で判定（ズレ防止）
        if (!empty($filters['has_title'])) {
            $query->whereRaw('coalesce(titles_agg.titles_count, 0) > 0');
        }
        if (!empty($filters['titles_from'])) {
            $query->whereRaw('coalesce(titles_agg.titles_count, 0) >= ?', [(int)$filters['titles_from']]);
        }
        if (!empty($filters['titles_to'])) {
            $query->whereRaw('coalesce(titles_agg.titles_count, 0) <= ?', [(int)$filters['titles_to']]);
        }

        // 他フラグ
        if (!empty($filters['is_district_leader'])) $query->where('is_district_leader', true);
        if (!empty($filters['has_sports_coach_license'])) $query->where('has_sports_coach_license', true);

        // コーチ名（pro_bowlers.coach を想定）
        if (!empty($filters['coach_name'])) {
            $coach = trim((string)$filters['coach_name']);
            $query->where('coach', 'like', "%{$coach}%");
        }

        // ====== ソート ======
        $sort = (string)($filters['sort'] ?? '');
        $dir  = strtolower((string)($filters['dir'] ?? 'asc'));
        $dir  = $dir === 'desc' ? 'desc' : 'asc';

        if ($sort === 'titles') {
            $query->orderByRaw('coalesce(titles_agg.titles_count, 0) ' . $dir);
        }

        // 既定の並び（最後に安定化）
        $query->orderByRaw('license_no_num asc nulls last')
            ->orderBy('license_no');

        $proBowlers = $query
            ->paginate(50)
            ->appends($filters);

        return view('pro_bowlers.list', compact('proBowlers', 'filters'));
    }


    /* =========================
       バリデーション定義
    ========================== */
    private function adminRules(): array
    {
        return [
            'license_no'         => 'required|string|max:255',
            'name'               => 'required|string|max:255',
            'furigana'           => 'nullable|string|max:255',
            'district'           => 'required|integer|exists:districts,id',
            'gender'             => 'required|in:男性,女性',
            'kibetsu'            => 'nullable|integer|min:1|max:99',
            'membership_type'    => 'nullable|string|max:255',
            'license_issue_date' => 'nullable|date',
            'phone_home'         => 'nullable|string|max:20',

            // 画像（ここを追加）
            'public_image_path'    => 'nullable|file|image|max:5120',
            'profile_image_public' => 'nullable|file|image|max:5120',
            'qr_code_path'         => 'nullable|file|image|max:5120',

            'birthdate'                   => 'nullable|date',
            'birthdate_public'            => 'nullable|date',
            'birthdate_public_hide_year'  => 'sometimes|boolean',
            'birthdate_public_is_private' => 'sometimes|boolean',
            'birthplace'         => 'nullable|string|max:255',
            'email'              => 'nullable|email|max:255',
            'work_place'         => 'nullable|string|max:255',
            'work_place_url'     => 'nullable|url',
            'mailing_preference' => 'nullable|in:1,2',
            'pro_entry_year'     => 'nullable|integer|min:1950|max:2099',
            'school'                 => 'nullable|string|max:255',
            'hobby'                  => 'nullable|string|max:255',
            'bowling_history'        => 'nullable|string|max:255',
            'other_sports_history'   => 'nullable|string|max:1000',
            'season_goal'            => 'nullable|string|max:255',
            'coach'                  => 'nullable|string|max:255',
            'selling_point'          => 'nullable|string|max:1000',
            'free_comment'           => 'nullable|string|max:1000',
            'facebook'               => 'nullable|url',
            'twitter'                => 'nullable|url',
            'instagram'              => 'nullable|url',
            'rankseeker'             => 'nullable|url',
            'jbc_driller_cert'       => 'nullable|in:有,無',
            'a_license_date'         => 'nullable|date',
            'permanent_seed_date'    => 'nullable|date',
            'hall_of_fame_date'      => 'nullable|date',
            'memo'                   => 'nullable|string|max:1000',
            'usbc_coach'             => 'nullable|in:Bronze,Silver,Gold',
            'is_district_leader'     => 'sometimes|boolean',

            // 選手編集可能項目も含めて全部
            'height_cm'              => 'nullable|integer|min:0|max:300',
            'height_is_public'       => 'sometimes|boolean',
            'weight_kg'              => 'nullable|integer|min:0|max:400',
            'weight_is_public'       => 'sometimes|boolean',
            'blood_type'             => 'nullable|string|max:3',
            'blood_type_is_public'   => 'sometimes|boolean',
            'dominant_arm'           => 'nullable|string|max:5',
            'sponsor_a'              => 'nullable|string|max:255',
            'sponsor_a_url'          => 'nullable|url|max:255',
            'sponsor_b'              => 'nullable|string|max:255',
            'sponsor_b_url'          => 'nullable|url|max:255',
            'sponsor_c'              => 'nullable|string|max:255',
            'sponsor_c_url'          => 'nullable|url|max:255',
            'equipment_contract'     => 'nullable|string|max:255',
            'coaching_history'       => 'nullable|string|max:2000',
            'motto'                  => 'nullable|string|max:255',

            'mailing_addr_same_as_org' => 'sometimes|boolean',
            'mailing_zip'   => 'nullable|string|max:10',
            'mailing_addr1' => 'nullable|string|max:255',
            'mailing_addr2' => 'nullable|string|max:255',
            'login_id'             => 'nullable|string|max:255',
            'mypage_temp_password' => 'nullable|string|max:255',
            'organization_name'  => 'nullable|string|max:255',
            'organization_zip'   => 'nullable|string|max:10',
            'organization_addr1' => 'nullable|string|max:255',
            'organization_addr2' => 'nullable|string|max:255',
            'organization_url'   => 'nullable|url|max:255',
            'public_addr_same_as_org' => 'sometimes|boolean',
            'public_zip'   => 'nullable|string|max:10',
            'public_addr1' => 'nullable|string|max:255',
            'public_addr2' => 'nullable|string|max:255',
            'password_change_status' => 'nullable|in:0,1,2,更新済,確認中,未更新',
            'a_license_number' => 'nullable|integer',
        ];
    }

    private function playerRules(): array
    {
        return [
            'height_cm'            => 'nullable|integer|min:0|max:300',
            'height_is_public'     => 'sometimes|boolean',
            'weight_kg'            => 'nullable|integer|min:0|max:400',
            'weight_is_public'     => 'sometimes|boolean',
            'blood_type'           => 'nullable|string|max:3',
            'blood_type_is_public' => 'sometimes|boolean',
            'dominant_arm'         => 'nullable|string|max:5',
            'hobby'                => 'nullable|string|max:255',
            'bowling_history'      => 'nullable|string|max:255',
            'other_sports_history' => 'nullable|string|max:1000',
            'season_goal'          => 'nullable|string|max:255',
            'coach'                => 'nullable|string|max:255',
            'sponsor_a'            => 'nullable|string|max:255',
            'sponsor_a_url'        => 'nullable|url|max:255',
            'sponsor_b'            => 'nullable|string|max:255',
            'sponsor_b_url'        => 'nullable|url|max:255',
            'sponsor_c'            => 'nullable|string|max:255',
            'sponsor_c_url'        => 'nullable|url|max:255',
            'equipment_contract'   => 'nullable|string|max:255',
            'coaching_history'     => 'nullable|string|max:2000',
            'motto'                => 'nullable|string|max:255',
            'selling_point'        => 'nullable|string|max:1000',
            'free_comment'         => 'nullable|string|max:1000',
            'facebook'             => 'nullable|url',
            'twitter'              => 'nullable|url',
            'instagram'            => 'nullable|url',
            'rankseeker'           => 'nullable|url',
            'jbc_driller_cert'     => 'nullable|in:有,無',
        ];
    }

    private function playerEditableKeys(): array
    {
        return [
            'height_cm','height_is_public',
            'weight_kg','weight_is_public',
            'blood_type','blood_type_is_public',
            'dominant_arm',
            'hobby','bowling_history','other_sports_history','season_goal','coach',
            'sponsor_a','sponsor_a_url','sponsor_b','sponsor_b_url','sponsor_c','sponsor_c_url',
            'equipment_contract','coaching_history','motto',
            'selling_point','free_comment',
            'facebook','twitter','instagram','rankseeker',
            'jbc_driller_cert',
        ];
    }

    /* =========================
       共通ビルド（管理者用）
    ========================== */
    private function buildAdminPayload(Request $request, array $validated): array
    {
        $hideYear  = $request->boolean('birthdate_public_hide_year');
        $isPrivate = $request->boolean('birthdate_public_is_private');
        $isLeader  = $request->boolean('is_district_leader');

        return [
            'license_no'         => $validated['license_no'],
            'name_kanji'         => $validated['name'],
            'name_kana'          => $validated['furigana'] ?? null,
            'sex'                => $validated['gender'] === '男性' ? 1 : 2,
            'district_id'        => (int)$validated['district'],
            'kibetsu'            => $validated['kibetsu'] ?? null,
            'membership_type'    => $validated['membership_type'] ?? null,
            'license_issue_date' => $validated['license_issue_date'] ?? null,
            'phone_home'         => $validated['phone_home'] ?? null,

            // ← ここに handleUploads が request->merge したパスが入る
            'qr_code_path'       => $request->input('qr_code_path') ?: null,
            'public_image_path'  => $request->input('public_image_path') ?: null,

            'birthdate'          => $this->ymd($request->input('birthdate')),
            'birthdate_public'   => $this->ymd($request->input('birthdate_public')),
            'birthdate_public_hide_year'  => $hideYear,
            'birthdate_public_is_private' => $isPrivate,
            'birthplace'         => $validated['birthplace'] ?? null,
            'email'              => $validated['email'] ?? null,
            'work_place'         => $validated['work_place'] ?? null,
            'work_place_url'     => $validated['work_place_url'] ?? null,
            'mailing_preference' => $validated['mailing_preference'] ?? null,
            'pro_entry_year'     => $validated['pro_entry_year'] ?? null,
            'school'             => $validated['school'] ?? null,
            'hobby'              => $validated['hobby'] ?? null,
            'bowling_history'    => $validated['bowling_history'] ?? null,
            'other_sports_history' => $validated['other_sports_history'] ?? null,
            'season_goal'        => $validated['season_goal'] ?? null,
            'coach'              => $validated['coach'] ?? null,
            'selling_point'      => $validated['selling_point'] ?? null,
            'free_comment'       => $validated['free_comment'] ?? null,
            'facebook'           => $validated['facebook'] ?? null,
            'twitter'            => $validated['twitter'] ?? null,
            'instagram'          => $validated['instagram'] ?? null,
            'rankseeker'         => $validated['rankseeker'] ?? null,
            'jbc_driller_cert'   => $validated['jbc_driller_cert'] ?? null,
            'a_license_date'     => $validated['a_license_date'] ?? null,
            'permanent_seed_date'=> $validated['permanent_seed_date'] ?? null,
            'hall_of_fame_date'  => $validated['hall_of_fame_date'] ?? null,
            'memo'               => $validated['memo'] ?? null,
            'usbc_coach'         => $validated['usbc_coach'] ?? null,
            'is_district_leader' => $isLeader ? 1 : 0,

            'height_cm'            => $this->intOrNull($request->input('height_cm'), 0, 300),
            'height_is_public'     => $request->boolean('height_is_public'),
            'weight_kg'            => $this->intOrNull($request->input('weight_kg'), 0, 400),
            'weight_is_public'     => $request->boolean('weight_is_public'),
            'blood_type'           => $this->normalizeBlood($request->input('blood_type')),
            'blood_type_is_public' => $request->boolean('blood_type_is_public'),
            'dominant_arm'         => $request->input('dominant_arm') ?: null,
            'sponsor_a'            => $this->nullIfBlank($request->input('sponsor_a')),
            'sponsor_a_url'        => $this->nullIfBlank($request->input('sponsor_a_url')),
            'sponsor_b'            => $this->nullIfBlank($request->input('sponsor_b')),
            'sponsor_b_url'        => $this->nullIfBlank($request->input('sponsor_b_url')),
            'sponsor_c'            => $this->nullIfBlank($request->input('sponsor_c')),
            'sponsor_c_url'        => $this->nullIfBlank($request->input('sponsor_c_url')),
            'equipment_contract'   => $this->nullIfBlank($request->input('equipment_contract')),
            'coaching_history'     => $this->nullIfBlank($request->input('coaching_history')),
            'motto'                => $this->nullIfBlank($request->input('motto')),
            'mailing_addr_same_as_org' => $request->boolean('mailing_addr_same_as_org'),
            'mailing_zip'          => $this->nullIfBlank($request->input('mailing_zip')),
            'mailing_addr1'        => $this->nullIfBlank($request->input('mailing_addr1')),
            'mailing_addr2'        => $this->nullIfBlank($request->input('mailing_addr2')),
            'login_id'             => $this->nullIfBlank($request->input('login_id')),
            'mypage_temp_password' => $this->nullIfBlank($request->input('mypage_temp_password')),
            'organization_name'    => $this->nullIfBlank($request->input('organization_name')),
            'organization_zip'     => $this->nullIfBlank($request->input('organization_zip')),
            'organization_addr1'   => $this->nullIfBlank($request->input('organization_addr1')),
            'organization_addr2'   => $this->nullIfBlank($request->input('organization_addr2')),
            'organization_url'     => $this->nullIfBlank($request->input('organization_url')),
            'public_addr_same_as_org' => $request->boolean('public_addr_same_as_org'),
            'public_zip'           => $this->nullIfBlank($request->input('public_zip')),
            'public_addr1'         => $this->nullIfBlank($request->input('public_addr1')),
            'public_addr2'         => $this->nullIfBlank($request->input('public_addr2')),
            'a_license_number'     => $request->filled('a_license_number') ? (int)$request->input('a_license_number') : null,
            'password_change_status' => $this->normalizePwdChangeStatus($request->input('password_change_status')),

            'a_class_status'         => $request->input('a_class_status'),
            'a_class_year'           => $request->input('a_class_year'),
            'b_class_status'         => $request->input('b_class_status'),
            'b_class_year'           => $request->input('b_class_year'),
            'c_class_status'         => $request->input('c_class_status'),
            'c_class_year'           => $request->input('c_class_year'),
            'master_status'          => $request->input('master_status'),
            'master_year'            => $request->input('master_year'),
            'coach_4_status'         => $request->input('coach_4_status'),
            'coach_4_year'           => $request->input('coach_4_year'),
            'coach_3_status'         => $request->input('coach_3_status'),
            'coach_3_year'           => $request->input('coach_3_year'),
            'coach_1_status'         => $request->input('coach_1_status'),
            'coach_1_year'           => $request->input('coach_1_year'),
            'kenkou_status'          => $request->input('kenkou_status'),
            'kenkou_year'            => $request->input('kenkou_year'),
            'school_license_status'  => $request->input('school_license_status'),
            'school_license_year'    => $request->input('school_license_year'),
        ];
    }

    /* =========================
       Instructor 同期
    ========================== */
    private function syncInstructor(Request $request, ProBowler $bowler): void
    {
        $grade = match (true) {
            $request->input('a_class_status') === '有' => 'A級',
            $request->input('b_class_status') === '有' => 'B級',
            $request->input('c_class_status') === '有' => 'C級',
            default => null,
        };

        $payload = [
            'license_no'          => $bowler->license_no,
            'name'                => $bowler->name_kanji,
            'name_kana'           => $bowler->name_kana,
            'sex'                 => ((int)$bowler->sex) === 1,
            'district_id'         => $bowler->district_id,
            'instructor_type'     => 'pro',
            'is_active'           => true,
            'is_visible'          => true,
            'coach_qualification' => ($bowler->school_license_status ?? $request->input('school_license_status')) === '有',
        ];
        if ($grade !== null) $payload['grade'] = $grade;

        Instructor::updateOrCreate(['pro_bowler_id' => $bowler->id], $payload);
    }

    /* =========================
       画像アップロード（/storage or /uploads）
    ========================== */
    private function handleUploads(Request $request, ?ProBowler $current): void
    {
        $useStorageLink = $this->ensurePublicStorageReady();

        // 共通クロージャ：保存してURLを返す
        $save = function (\Illuminate\Http\UploadedFile $file, string $subdir) use ($useStorageLink): string {
            if ($useStorageLink) {
                $p = $file->store($subdir, 'public');            // storage/app/public/...
                return '/storage/' . $p;                          // ブラウザ公開URL
            } else {
                $dir = public_path('uploads/'.$subdir);
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $name = date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$file->getClientOriginalExtension();
                $file->move($dir, $name);
                return '/uploads/'.$subdir.'/'.$name;
            }
        };

        // プロフィール写真（name="profile_image_public" または "public_image_path"）
        if ($request->hasFile('profile_image_public') && $request->file('profile_image_public')->isValid()) {
            $url = $save($request->file('profile_image_public'), 'profiles');
            if ($current && $current->public_image_path) $this->deleteOldPublicFile($current->public_image_path);
            $request->merge(['public_image_path' => $url]);
        } elseif ($request->hasFile('public_image_path') && $request->file('public_image_path')->isValid()) {
            $url = $save($request->file('public_image_path'), 'profiles');
            if ($current && $current->public_image_path) $this->deleteOldPublicFile($current->public_image_path);
            $request->merge(['public_image_path' => $url]);
        }

        // QRコード画像
        if ($request->hasFile('qr_code_path') && $request->file('qr_code_path')->isValid()) {
            $url = $save($request->file('qr_code_path'), 'qrs');
            if ($current && $current->qr_code_path) $this->deleteOldPublicFile($current->qr_code_path);
            $request->merge(['qr_code_path' => $url]);
        }
    }

    private function ensurePublicStorageReady(): bool
    {
        $pub = public_path('storage');
        if (is_link($pub) || is_dir($pub)) return true;

        try { Artisan::call('storage:link'); } catch (\Throwable $e) {}
        return (is_link($pub) || is_dir($pub));
    }

    private function deleteOldPublicFile(string $urlOrPath): void
    {
        $path = parse_url($urlOrPath, PHP_URL_PATH) ?: $urlOrPath; // '/storage/..' or '/uploads/..'
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            // /storage/xxx -> disk('public')->delete('xxx')
            $rel = substr($path, strlen('storage/'));
            try { Storage::disk('public')->delete($rel); } catch (\Throwable $e) {}
        } elseif (str_starts_with($path, 'uploads/')) {
            @unlink(public_path($path));
        }
    }

    /* =========================
       小物ユーティリティ
    ========================== */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403, '管理者のみ実行できます。');
    }

    private function intOrNull($v, int $min, int $max): ?int
    {
        if ($v === null || $v === '') return null;
        $n = (int) preg_replace('/\D/', '', (string) $v);
        return max($min, min($max, $n));
    }

    private function normalizeBlood(?string $v): ?string
    {
        if ($v === null || $v === '') return null;
        $s = strtoupper(mb_convert_kana($v, 'as'));
        $map = ['A型'=>'A','B型'=>'B','AB型'=>'AB','O型'=>'O'];
        $s = $map[$s] ?? $s;
        return in_array($s, ['A','B','AB','O'], true) ? $s : null;
    }

    private function normalizePwdChangeStatus($v): ?int {
        if ($v === null || $v === '') return null;
        $map = ['更新済' => 0, '確認中' => 1, '未更新' => 2];
        if (isset($map[$v])) return $map[$v];
        $n = (int)$v;
        return in_array($n, [0,1,2], true) ? $n : null;
    }

    private function ymd(?string $v): ?string {
        if (!$v) return null;
        try {
            $v = str_replace(['/', '.'], '-', $v);
            return \Carbon\Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function editSelf()
    {
        $user = auth()->user();
        abort_unless($user && $user->pro_bowler_id, 403, '選手IDが紐付いていません。');
        return $this->edit($user->pro_bowler_id);
    }

    private function nullIfBlank($v) {
        $s = preg_replace('/^[\h\v\p{Zs}\p{Zl}\p{Zp}]+|[\h\v\p{Zs}\p{Zl}\p{Zp}]+$/u', '', (string)$v);
        return $s === '' ? null : $s;
    }
}
