<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\{Group, GroupMember, ProBowler, Tournament, District};
use App\Services\GroupRuleEngine;

class ProGroupController extends Controller
{
    public function index()
    {
        $groups = Group::withCount('members')->orderBy('name')->get();
        return view('pro_groups.index', compact('groups'));
    }

    public function show(Group $pro_group)
    {
        $group = $pro_group->load(['members' => function($q){
            $q->with('district')->orderBy('license_no');
        }]);
        return view('pro_groups.show', compact('group'));
    }

    public function create(Request $r)
    {
        $group = new Group(['type'=>'rule','retention'=>'forever']);
        // ★ 地区は ID 昇順に
        $districts   = District::query()->orderBy('id')->get(['id','label']);
        $tournaments = Tournament::query()->orderByDesc('year')->orderBy('id')->get(['id','name','year']);

        $preset  = $r->query('preset');
        $preset_district_id   = $r->query('district_id');
        $preset_tournament_id = $r->query('tournament_id');

        return view('pro_groups.edit', compact('group','districts','tournaments','preset','preset_district_id','preset_tournament_id'));
    }

    public function store(Request $r, GroupRuleEngine $engine)
    {
        $payload = $this->normalizeByPreset($r, mode: 'create');
        $data = $this->validateGroup(new Request($payload));
        $g = Group::create($data);
        if ($g->type === 'rule') $this->rebuild($g, $engine);
        return redirect()->route('pro_groups.show', $g)->with('success','グループを作成しました');
    }

    public function edit(Group $pro_group)
    {
        $districts   = District::query()->orderBy('id')->get(['id','label']);
        $tournaments = Tournament::query()->orderByDesc('year')->orderBy('id')->get(['id','name','year']);
        return view('pro_groups.edit', ['group'=>$pro_group,'districts'=>$districts,'tournaments'=>$tournaments]);
    }

    public function update(Request $r, Group $pro_group)
    {
        $payload = $this->normalizeByPreset($r, mode: 'edit', current: $pro_group);
        $data = $this->validateGroup(new Request($payload));
        $pro_group->update($data);
        return redirect()->route('pro_groups.show', $pro_group)->with('success','更新しました');
    }

    /** 管理者のみ削除 */
    public function destroy(Group $pro_group)
    {
        if (!auth()->user()?->isAdmin()) abort(403);
        DB::transaction(function() use ($pro_group){
            GroupMember::where('group_id', $pro_group->id)->delete();
            $pro_group->delete();
        });
        return redirect()->route('pro_groups.index')->with('success','削除しました');
    }

    /** 入力→プリセット適用→空欄自動補完＋キー重複時は自動採番 */
    private function normalizeByPreset(Request $r, string $mode='create', ?Group $current=null): array
    {
        $base = [
            'key'   => $r->input('key'),       // 画面では hidden
            'name'  => $r->input('name'),
            'type'  => $r->input('type') ?: 'rule',
            'retention' => $r->input('retention') ?: 'forever',
            'expires_at'=> $r->input('expires_at'),
            'show_on_mypage' => (bool)$r->input('show_on_mypage'),
            'preset'=> $r->input('preset'),
            'action_mypage' => (bool)$r->input('action_mypage'),
            'action_email'  => (bool)$r->input('action_email'),
            'action_postal' => (bool)$r->input('action_postal'),
            'rule_json'     => $r->input('rule_json'),
        ];

        if ($base['preset']) {
            $params = [
                'district_id'   => $r->input('preset_district_id'),
                'tournament_id' => $r->input('preset_tournament_id'),
            ];
            [$autoKey,$autoName,$autoType,$autoRetention,$rule] = $this->buildPreset($base['preset'], $params);

            if (empty($base['key']))        $base['key']       = $autoKey;
            if (empty($base['name']))       $base['name']      = $autoName;
            if (empty($r->input('type')))   $base['type']      = $autoType;
            if (empty($r->input('retention'))) $base['retention'] = $autoRetention;

            $base['rule_json'] = json_encode($rule, JSON_UNESCAPED_UNICODE);
        }

        // 名前からキー自動生成（キー空なら）
        if (empty($base['key']) && !empty($base['name'])) {
            $slug = Str::slug($base['name']) ?: 'group';
            $base['key'] = $slug;
        }

        // ★ キーのユニーク化：作成時は必ず、編集時は他レコードと衝突するなら採番
        $existsQ = Group::query()->where('key', $base['key'] ?? '');
        if ($mode === 'edit' && $current) $existsQ->where('id', '<>', $current->id);
        if (!empty($base['key']) && $existsQ->exists()) {
            $orig = $base['key'];
            $i = 2;
            while (Group::where('key', $orig.'-'.$i)->exists()) $i++;
            $base['key'] = $orig.'-'.$i; // 例: district-leader-2
        }

        return $base;
    }

    private function validateGroup(Request $r): array
    {
        $data = $r->validate([
            'key'   => 'required|string|max:100',
            'name'  => 'required|string|max:100',
            'type'  => 'required|in:rule,snapshot',
            'retention' => 'required|in:forever,fye,until',
            'expires_at'=> 'nullable|date',
            'show_on_mypage' => 'boolean',
            'preset' => 'nullable|string|max:100',
            'action_mypage' => 'boolean',
            'action_email'  => 'boolean',
            'action_postal' => 'boolean',
            'rule_json' => 'nullable|string',
        ]);
        $data['rule_json'] = $data['rule_json'] ? json_decode($data['rule_json'], true) : null;
        return $data;
    }

    /** プリセット（$id のエラーを文字連結で完全撤去） */
    private function buildPreset(string $preset, array $params): array
    {
        return match($preset) {
            'gender-m' => ['gender-m','男子プロ','rule','forever', ['attr'=>'sex','eq'=>1]],
            'gender-f' => ['gender-f','女子プロ','rule','forever', ['attr'=>'sex','eq'=>2]],
            'district-leader' => ['district-leader','地区長連絡','rule','fye', ['attr'=>'is_district_leader','eq'=>true]],
            'title-holder'    => ['title-holder','タイトルホルダー','rule','forever', ['exists'=>'titles']],
            'license-a'       => ['license-a','A級ライセンス保有','rule','forever', ['attr'=>'a_license_number','neq'=>null]],
            'instructor-b'    => ['instructor-b','B級インストラクター','rule','forever', ['attr'=>'b_class_status','eq'=>'有']],
            'instructor-c-only'=>['instructor-c-only','C級インストラクターのみ','rule','forever', ['and'=>[
                ['attr'=>'c_class_status','eq'=>'有'],
                ['attr'=>'a_class_status','neq'=>'有'],
                ['attr'=>'b_class_status','neq'=>'有'],
            ]]],
            'training-missing-or-expired' => ['training-missing-or-expired','講習 未受講/期限切れ','rule','forever', ['or'=>[
                ['attr'=>'compliance_status','eq'=>'missing'],
                ['attr'=>'compliance_status','eq'=>'expired'],
            ]]],
            'dues-unpaid-this-year' => ['dues-unpaid-this-year','年会費 未納（今年）','rule','fye', ['annual_dues'=>['year'=>'current','paid'=>false]]],
            'district' => (function() use ($params) {
                $id = (int)($params['district_id'] ?? 0);
                if ($id <= 0) abort(422,'地区を選択してください');
                return [
                    'district-'.$id,
                    '該当地区プロ（ID:'.$id.'）',
                    'rule','forever',
                    ['attr'=>'district_id','eq'=>$id]
                ];
            })(),
            'tournament' => (function() use ($params) {
                $tid = (int)($params['tournament_id'] ?? 0);
                if ($tid <= 0) abort(422,'大会を選択してください');
                return [
                    'tournament-'.$tid,
                    '大会('.$tid.') 参加者',
                    'rule','fye',
                    ['tournament_participant'=>['tournament_id'=>$tid]]
                ];
            })(),
            default => abort(422, "未知のプリセット: $preset"),
        };
    }

    public function rebuild(Group $pro_group, GroupRuleEngine $engine)
    {
        if ($pro_group->type !== 'rule') abort(400, 'rule グループ以外は再計算不要');

        $today = today();
        $expire = match($pro_group->retention){
            'fye'   => Carbon::create($today->year, 12, 31),
            'until' => $pro_group->expires_at,
            default => null,
        };

        DB::transaction(function () use ($pro_group, $engine, $expire) {
            GroupMember::where('group_id',$pro_group->id)->delete();

            ProBowler::query()->withCount('titles')->chunkById(1000, function($bowlers) use ($pro_group,$engine,$expire){
                $inserts = [];
                foreach ($bowlers as $b) {
                    if ($engine->matches($b, $pro_group->rule_json ?? [])) {
                        $inserts[] = [
                            'group_id' => $pro_group->id,
                            'pro_bowler_id' => $b->id,
                            'source' => 'rule',
                            'assigned_at' => now(),
                            'expires_at' => $expire,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
                if ($inserts) DB::table('group_members')->insert($inserts);
            });
        });

        return back()->with('success','再計算しました');
    }

    public function exportCsv(Group $pro_group)
    {
        $rows = $pro_group->members()->select([
            'pro_bowlers.license_no','pro_bowlers.name_kanji',
            'pro_bowlers.mailing_zip','pro_bowlers.mailing_addr1','pro_bowlers.mailing_addr2',
        ])->get();

        $csv = implode(",", ['license_no','name','zip','addr1','addr2'])."\n";
        foreach ($rows as $r) {
            $line = [$r->license_no,$r->name_kanji,$r->mailing_zip,$r->mailing_addr1,$r->mailing_addr2];
            $csv .= implode(",", array_map(fn($v)=>'"'.str_replace('"','""',$v ?? '').'"', $line))."\n";
        }
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="group_'.$pro_group->key.'.csv"',
        ]);
    }

    public function quickCreateTournamentGroup(\App\Models\Tournament $tournament, \App\Services\GroupRuleEngine $engine)
    {
        $u = auth()->user();
        if (!$u || (!$u->isAdmin() && !$u->isEditor())) abort(403);
        // 例: tournament-3, 重複してたら tournament-3-2 …
        $baseKey = 'tournament-'.$tournament->id;
        $key = $baseKey;
        $i = 2;
        while (\App\Models\Group::where('key',$key)->exists()) {
            $key = $baseKey.'-'.$i++;
        }

        $name = trim(($tournament->year ? $tournament->year.' ' : '').$tournament->name).' 参加者';

        $group = \App\Models\Group::create([
            'key'        => $key,
            'name'       => $name,
            'type'       => 'rule',
            'retention'  => 'fye', // 年度末まで保持
            'preset'     => 'tournament',
            'rule_json'  => ['tournament_participant' => ['tournament_id' => $tournament->id]],
            // お好みで既定のアクション
            'show_on_mypage' => false,
            'action_postal'  => true,   // 郵送リスト想定でONにしておく
        ]);

        // 参加者でメンバー埋める
        $this->rebuild($group, $engine);

        return redirect()->route('pro_groups.show', $group)
            ->with('success', '「'.$name.'」グループを作成しました。');
    }

}
