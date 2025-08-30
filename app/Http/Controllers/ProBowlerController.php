<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProBowler;
use App\Models\District;
use App\Models\Instructor;

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

        $bowler = ProBowler::create($data);
        $this->syncInstructor($request, $bowler);

        $back = $request->input('return')
            ?: route('pro_bowlers.list', session('pro_bowlers.last_filters', []));
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
       更新（管理者：全項目 / 選手：自己の“選手編集可能項目”のみ）
       ※この update は従来どおり（管理ルート向け）。自己更新は updateSelf を使用。
    ========================== */
    public function update(Request $request, $id)
    {
        $bowler   = ProBowler::findOrFail($id);
        $isAdmin  = auth()->user()?->isAdmin();
        $isSelf   = auth()->user()?->pro_bowler_id === $bowler->id;

        abort_unless($isAdmin || $isSelf, 403, '権限がありません。');

        if ($isAdmin) {
            $validated = $request->validate($this->adminRules());
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
            // 自己更新（プレイヤー）
            return $this->updateSelf($request, $bowler);
        }

        $back = $request->input('return')
            ?: route('pro_bowlers.list', session('pro_bowlers.last_filters', []));
        return redirect()->to($back)->with('success', '更新完了');
    }

    /* =========================
       一般ユーザー用の自己更新ルート
    ========================== */
    public function updateSelf(Request $request, ProBowler $bowler)
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || $user->pro_bowler_id === $bowler->id), 403, '権限がありません。');

        $request->validate($this->playerRules());

        $payload = [];
        foreach ($this->playerEditableKeys() as $k) {
            switch ($k) {
                case 'height_cm':
                    $payload[$k] = $this->intOrNull($request->input($k), 0, 300); break;
                case 'weight_kg':
                    $payload[$k] = $this->intOrNull($request->input($k), 0, 400); break;
                case 'blood_type':
                    $payload[$k] = $this->normalizeBlood($request->input($k)); break;
                case 'height_is_public':
                case 'weight_is_public':
                case 'blood_type_is_public':
                    $payload[$k] = $request->boolean($k); break;
                default:
                    $payload[$k] = $this->nullIfBlank($request->input($k));
            }
        }

        $bowler->update($payload);

        // 自己更新後はマイページ等へ戻す
        return redirect()->route('athlete.index')->with('success', 'プロフィールを更新しました');
    }

    /* =========================
       検索つき一覧（list）
    ========================== */
    public function list(Request $request)
    {
        $filters = $request->query();
        session(['pro_bowlers.last_filters' => $filters]);

        $titleYear = $request->integer('title_year');
        $titleFrom = $request->integer('title_year_from');
        $titleTo   = $request->integer('title_year_to');

        $query = ProBowler::with('district')->withCount('titles');

        if ($titleYear) {
            $query->withCount([
                'titles as titles_count_'.$titleYear => fn($q) => $q->where('year', $titleYear)
            ]);
        }
        if ($titleFrom && $titleTo) {
            $query->withCount([
                'titles as titles_count_range' => fn($q) => $q->whereBetween('year', [$titleFrom, $titleTo])
            ]);
        }

        if ($request->filled('license_no'))          $query->where('license_no', 'like', '%'.$request->license_no.'%');
        if ($request->filled('pro_entry_year_from')) $query->where('pro_entry_year', '>=', $request->pro_entry_year_from);
        if ($request->filled('pro_entry_year_to'))   $query->where('pro_entry_year', '<=', $request->pro_entry_year_to);
        if ($request->filled('name'))                $query->where('pro_bowlers.name_kanji', 'like', '%'.$request->name.'%');
        if ($request->filled('id_from'))             $query->where('pro_bowlers.id', '>=', $request->id_from);
        if ($request->filled('id_to'))               $query->where('pro_bowlers.id', '<=', $request->id_to);
        if ($request->filled('district'))            $query->whereHas('district', fn($q) => $q->where('label', $request->district));
        if ($request->filled('gender'))              $query->where('pro_bowlers.sex', $request->gender === '男性' ? 1 : 2);
        if ($request->filled('age_from'))            $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, birthdate)) >= ?', [$request->age_from]);
        if ($request->filled('age_to'))              $query->whereRaw('EXTRACT(YEAR FROM AGE(CURRENT_DATE, birthdate)) <= ?', [$request->age_to]);
        if ($request->boolean('has_title'))          $query->has('titles');
        if ($request->boolean('has_sports_coach_license')) {
            $query->where(function($q){
                $q->where('coach_1_status','有')
                  ->orWhere('coach_3_status','有')
                  ->orWhere('coach_4_status','有');
            });
        }
        if ($request->boolean('is_district_leader')) $query->where('is_district_leader', true);

        $bowlers = $query->paginate(10)->appends($filters);

        $districtOrder = ['北海道','東北','北関東','埼玉','千葉','城東','城南','城西','三多摩','神奈川・東','神奈川・西','静岡','甲信越','東海','北陸','関西・東','関西・西','関西・南','中国四国','九州・北','九州･南／沖縄','海外'];
        $districts = District::all()
            ->sortBy(fn($d) => array_search($d->label, $districtOrder))
            ->pluck('label', 'id');

        return view('pro_bowlers.list', compact('bowlers', 'districts', 'filters'));
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
        // 選手が触れる項目だけ
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
       Instructor 同期（store/update 共通）
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
            'sex'                 => ((int)$bowler->sex) === 1,       // true: 男性
            'district_id'         => $bowler->district_id,
            'instructor_type'     => 'pro',
            'is_active'           => true,
            'is_visible'          => true,
            'coach_qualification' => ($bowler->school_license_status ?? $request->input('school_license_status')) === '有',
        ];
        if ($grade !== null) {
            $payload['grade'] = $grade;
        }

        Instructor::updateOrCreate(
            ['pro_bowler_id' => $bowler->id],
            $payload
        );
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

    // 血液型を A/B/AB/O のいずれかに正規化（それ以外は null）
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
        // 前後の空白（半角/全角/ノーブレーク/改行など）をまとめて除去
        $s = preg_replace('/^[\h\v\p{Zs}\p{Zl}\p{Zp}]+|[\h\v\p{Zs}\p{Zl}\p{Zp}]+$/u', '', (string)$v);
        return $s === '' ? null : $s;
    }

}
