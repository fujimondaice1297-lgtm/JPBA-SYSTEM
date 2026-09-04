<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Information;
use App\Services\ManagedPublicPageSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InformationAdminController extends Controller
{
    private function categories(): array
    {
        return Information::categories();
    }

    private function audiences(): array
    {
        return ['public', 'members', 'district_leaders', 'needs_training'];
    }

    private function years(): array
    {
        return DB::table('informations')
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM COALESCE(published_at, updated_at, starts_at, created_at))::int AS y')
            ->orderByDesc('y')
            ->pluck('y')
            ->all();
    }

    public function index(Request $request)
    {
        $year = $request->input('year');
        $category = trim((string) $request->input('category', ''));
        $audience = trim((string) $request->input('audience', ''));

        if ($category === '' || ! in_array($category, $this->categories(), true)) {
            $category = null;
        }
        if ($audience === '' || ! in_array($audience, $this->audiences(), true)) {
            $audience = null;
        }

        $infos = Information::query()
            ->when($year !== null && $year !== '', fn ($q) => $q->whereYear(DB::raw('COALESCE(published_at, updated_at, starts_at, created_at)'), (int) $year))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($audience, fn ($q) => $q->where('audience', $audience))
            ->orderByDesc(DB::raw('COALESCE(published_at, updated_at, starts_at, created_at)'))
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
            'information' => new Information,
            'categories' => $this->categories(),
            'audiences' => $this->audiences(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        $info = DB::transaction(function () use ($request, $data): Information {
            $info = new Information;
            $info->forceFill($this->informationValues($request, $data))->save();
            $this->storeNewAttachments($info, $request);

            return $info;
        });

        return redirect()->route('admin.informations.edit', $info)->with('success', '作成しました');
    }

    public function edit(Request $request, Information $information)
    {
        $information->load(['files' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);

        return view('admin.informations.edit', [
            'information' => $information,
            'categories' => $this->categories(),
            'audiences' => $this->audiences(),
        ]);
    }

    public function update(Request $request, Information $information, ManagedPublicPageSanitizer $sanitizer)
    {
        $data = $request->validate($this->validationRules(true));

        DB::transaction(function () use ($request, $data, $information, $sanitizer): void {
            $values = $this->informationValues($request, $data);
            $values['body'] = $information->body_format === 'html'
                ? $sanitizer->sanitize($data['body'])
                : $data['body'];

            if ($information->source_type) {
                $values['source_fingerprint'] = hash('sha256', 'manual-edit:'.$information->id.':'.now()->format('c.u'));
            }

            $information->forceFill($values)->save();
            $this->updateExistingAttachments($information, $request);
            $this->storeNewAttachments($information, $request);
        });

        return back()->with('success', '更新しました');
    }

    /** @return array<string,string> */
    private function validationRules(bool $updating = false): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'category' => Information::categoryValidationRule(),
            'audience' => 'required|in:public,members,district_leaders,needs_training',
            'is_public' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'required_training_id' => 'nullable|integer',
            'body' => 'required|string',
            'attachments' => 'nullable|array|max:20',
            'attachments.*' => 'file|max:20480|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,zip',
        ];

        if ($updating) {
            $rules += [
                'existing_attachment_titles' => 'nullable|array',
                'existing_attachment_titles.*' => 'nullable|string|max:255',
                'existing_attachment_visibility' => 'nullable|array',
                'existing_attachment_visibility.*' => 'nullable|in:public,members',
                'remove_attachment_ids' => 'nullable|array',
                'remove_attachment_ids.*' => 'integer',
            ];
        }

        return $rules;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function informationValues(Request $request, array $data): array
    {
        return [
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'audience' => $data['audience'],
            'is_public' => (bool) $request->boolean('is_public'),
            'published_at' => $data['published_at'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'required_training_id' => $data['required_training_id'] ?? null,
            'body' => $data['body'],
        ];
    }

    private function updateExistingAttachments(Information $information, Request $request): void
    {
        $removeIds = collect($request->input('remove_attachment_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->all();
        $titles = $request->input('existing_attachment_titles', []);
        $visibilities = $request->input('existing_attachment_visibility', []);

        foreach ($information->files()->get() as $file) {
            if (in_array($file->id, $removeIds, true)) {
                // 旧サイト取込ファイルは複数記事で共有される場合があるため、実体は保全して参照だけ外す。
                $file->delete();

                continue;
            }

            $file->forceFill([
                'title' => trim((string) ($titles[$file->id] ?? $file->title)),
                'visibility' => in_array(($visibilities[$file->id] ?? null), ['public', 'members'], true)
                    ? $visibilities[$file->id]
                    : $file->visibility,
            ])->save();
        }
    }

    private function storeNewAttachments(Information $information, Request $request): void
    {
        $nextSort = (int) $information->files()->max('sort_order') + 1;

        foreach ($request->file('attachments', []) as $upload) {
            $path = $upload->store('informations/'.$information->id, 'public');
            $mime = strtolower((string) $upload->getMimeType());
            $type = str_starts_with($mime, 'image/') ? 'image' : ($mime === 'application/pdf' ? 'pdf' : 'file');

            $information->files()->create([
                'type' => $type,
                'title' => Str::limit($upload->getClientOriginalName(), 255, ''),
                'file_path' => $path,
                'visibility' => $information->is_public ? 'public' : 'members',
                'sort_order' => $nextSort++,
            ]);
        }
    }
}
