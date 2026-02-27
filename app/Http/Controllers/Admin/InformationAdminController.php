<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Information;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformationAdminController extends Controller
{
    private function categories(): array
    {
        return ['NEWS', 'イベント', '大会', 'ｲﾝｽﾄﾗｸﾀｰ'];
    }

    private function audiences(): array
    {
        return ['public', 'members', 'district_leaders', 'needs_training'];
    }

    private function years(): array
    {
        return DB::table('informations')
            ->selectRaw("DISTINCT EXTRACT(YEAR FROM COALESCE(updated_at, starts_at, created_at))::int AS y")
            ->orderByDesc('y')
            ->pluck('y')
            ->all();
    }

    public function index(Request $request)
    {
        $year = $request->input('year');
        $category = trim((string)$request->input('category', ''));
        $audience = trim((string)$request->input('audience', ''));

        if ($category === '' || !in_array($category, $this->categories(), true)) $category = null;
        if ($audience === '' || !in_array($audience, $this->audiences(), true)) $audience = null;

        $infos = Information::query()
            ->when($year !== null && $year !== '', fn($q) => $q->whereYear('updated_at', (int)$year))
            ->when($category, fn($q) => $q->where('category', $category))
            ->when($audience, fn($q) => $q->where('audience', $audience))
            ->orderByDesc('updated_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.informations.index', [
            'infos' => $infos,
            'availableYears' => $this->years(),
            'categories' => $this->categories(),
            'audiences' => $this->audiences(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.informations.create', [
            'information' => new Information(),
            'categories' => $this->categories(),
            'audiences' => $this->audiences(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|in:NEWS,イベント,大会,ｲﾝｽﾄﾗｸﾀｰ',
            'audience' => 'required|in:public,members,district_leaders,needs_training',
            'is_public' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'required_training_id' => 'nullable|integer',
            'body' => 'required|string',
        ]);

        $info = new Information();
        $info->forceFill([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'audience' => $data['audience'],
            'is_public' => (bool)$request->boolean('is_public'),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'required_training_id' => $data['required_training_id'] ?? null,
            'body' => $data['body'],
        ])->save();

        return redirect()->route('admin.informations.edit', $info)->with('success', '作成しました');
    }

    public function edit(Request $request, Information $information)
    {
        return view('admin.informations.edit', [
            'information' => $information,
            'categories' => $this->categories(),
            'audiences' => $this->audiences(),
        ]);
    }

    public function update(Request $request, Information $information)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|in:NEWS,イベント,大会,ｲﾝｽﾄﾗｸﾀｰ',
            'audience' => 'required|in:public,members,district_leaders,needs_training',
            'is_public' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'required_training_id' => 'nullable|integer',
            'body' => 'required|string',
        ]);

        $information->forceFill([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'audience' => $data['audience'],
            'is_public' => (bool)$request->boolean('is_public'),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'required_training_id' => $data['required_training_id'] ?? null,
            'body' => $data['body'],
        ])->save();

        return back()->with('success', '更新しました');
    }
}