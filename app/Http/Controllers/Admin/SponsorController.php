<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SponsorController extends Controller
{
    public function index(): View
    {
        return view('admin.sponsors.index', [
            'sponsors' => Sponsor::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.sponsors.edit', [
            'sponsor' => new Sponsor([
                'is_published' => true,
                'sort_order' => 100,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_published'] = $request->boolean('is_published');
        $data['created_by_user_id'] = $request->user()?->id;
        $data['updated_by_user_id'] = $request->user()?->id;
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('sponsor-banners', 'public');
        }
        unset($data['logo']);

        $sponsor = Sponsor::query()->create($data);

        return redirect()->route('admin.sponsors.edit', $sponsor)
            ->with('success', '協賛バナーを登録しました。');
    }

    public function edit(Sponsor $sponsor): View
    {
        return view('admin.sponsors.edit', compact('sponsor'));
    }

    public function update(Request $request, Sponsor $sponsor): RedirectResponse
    {
        $data = $this->validated($request, $sponsor);
        $data['is_published'] = $request->boolean('is_published');
        $data['updated_by_user_id'] = $request->user()?->id;
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('sponsor-banners', 'public');
        }
        unset($data['logo']);

        $sponsor->update($data);

        return back()->with('success', '協賛バナーを保存しました。');
    }

    private function validated(Request $request, ?Sponsor $sponsor = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => [$sponsor?->logo_path ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65000'],
        ]);
    }
}
