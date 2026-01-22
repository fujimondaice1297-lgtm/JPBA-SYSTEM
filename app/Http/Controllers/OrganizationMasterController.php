<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMaster;
use Illuminate\Http\Request;

class OrganizationMasterController extends Controller
{
    // 管理画面（会場と同様に簡素）
    public function index(Request $request)
    {
        $q = OrganizationMaster::query();
        if ($kw = trim((string)$request->get('keyword',''))) {
            $q->where('name','ILIKE',"%{$kw}%");
        }
        $items = $q->orderBy('name')->paginate(20);
        return view('organizations.index', compact('items'));
    }

    public function create()
    {
        $org = new OrganizationMaster();
        return view('organizations.create', compact('org'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:255',
            'url' =>'nullable|url|max:255',
        ]);
        OrganizationMaster::create($data);
        return redirect()->route('organizations.index')->with('success','登録しました。');
    }

    public function edit($id)
    {
        $org = OrganizationMaster::findOrFail($id);
        return view('organizations.edit', compact('org'));
    }

    public function update(Request $request, $id)
    {
        $org = OrganizationMaster::findOrFail($id);
        $data = $request->validate([
            'name'=>'required|string|max:255',
            'url' =>'nullable|url|max:255',
        ]);
        $org->update($data);
        return redirect()->route('organizations.index')->with('success','更新しました。');
    }

    public function destroy($id)
    {
        OrganizationMaster::findOrFail($id)->delete();
        return redirect()->route('organizations.index')->with('success','削除しました。');
    }

    // === API ===
    public function search(Request $request)
    {
        $kw = trim((string)$request->query('q',''));
        $items = OrganizationMaster::query()
            ->when($kw !== '', fn($q)=>$q->where('name','ILIKE',"%{$kw}%"))
            ->orderBy('name')
            ->limit(30)
            ->get(['id','name','url']);
        return response()->json($items);
    }

    public function show($id)
    {
        $v = OrganizationMaster::findOrFail($id,['id','name','url']);
        return response()->json($v);
    }
}
