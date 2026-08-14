<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManagedPublicPage;
use App\Services\ManagedPublicPageSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManagedPublicPageController extends Controller
{
    public function index(): View
    {
        return view('admin.public_pages.index', [
            'pages' => ManagedPublicPage::query()
                ->orderBy('navigation_group')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.public_pages.edit', [
            'page' => new ManagedPublicPage([
                'is_published' => true,
                'navigation_group' => 'other',
                'sort_order' => 100,
            ]),
        ]);
    }

    public function store(Request $request, ManagedPublicPageSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->validated($request);
        $data['body_html'] = $sanitizer->sanitize($data['body_html']);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published'] ? now() : null;
        $data['source_checked_at'] = !empty($data['source_url']) ? now() : null;
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();

        $page = ManagedPublicPage::query()->create($data);

        return redirect()->route('admin.public_pages.edit', $page)
            ->with('success', '公開ページを作成しました。');
    }

    public function edit(ManagedPublicPage $publicPage): View
    {
        return view('admin.public_pages.edit', ['page' => $publicPage]);
    }

    public function update(
        Request $request,
        ManagedPublicPage $publicPage,
        ManagedPublicPageSanitizer $sanitizer,
    ): RedirectResponse {
        $data = $this->validated($request, $publicPage);
        $data['body_html'] = $sanitizer->sanitize($data['body_html']);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published']
            ? ($publicPage->published_at ?: now())
            : null;
        $data['source_checked_at'] = !empty($data['source_url'])
            ? ($publicPage->source_checked_at ?: now())
            : null;
        $data['updated_by_user_id'] = auth()->id();

        $publicPage->update($data);

        return back()->with('success', '公開ページを保存しました。');
    }

    private function validated(Request $request, ?ManagedPublicPage $page = null): array
    {
        return $request->validate([
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9][a-z0-9\-]*$/',
                Rule::unique('managed_public_pages', 'slug')->ignore($page?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:500000'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'navigation_group' => ['nullable', 'in:association,instructor,protest,footer,other'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_published' => ['nullable', 'boolean'],
        ]);
    }
}
