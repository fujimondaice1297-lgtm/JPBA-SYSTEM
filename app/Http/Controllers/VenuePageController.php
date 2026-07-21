<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\VenueNameNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VenuePageController extends Controller
{
    // 一覧
    public function index(Request $request)
    {
        $q = Venue::query();

        $status = (string) $request->query('status', 'active');
        if ($status === 'active') {
            $q->where('is_active', true);
        } elseif ($status === 'inactive') {
            $q->where('is_active', false);
        }

        if ($request->filled('keyword')) {
            $kw = trim($request->keyword);
            $q->where(function ($query) use ($kw) {
                $query->where('name', 'ILIKE', "%{$kw}%")
                    ->orWhere('address', 'ILIKE', "%{$kw}%");
            });
        }

        $venues = $q->orderBy('name')->get();

        return view('venues.index', compact('venues'));
    }

    // 会場検索API（大会create/edit用）
    public function search(Request $request, VenueNameNormalizer $normalizer)
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $normalizedQuery = $normalizer->normalize($q);
        $venues = Venue::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'aliases', 'address', 'tel', 'fax', 'website_url'])
            ->filter(function (Venue $venue) use ($normalizer, $normalizedQuery) {
                return collect([$venue->name, ...($venue->aliases ?? [])])
                    ->contains(fn ($name) => str_contains($normalizer->normalize($name), $normalizedQuery));
            })
            ->take(20)
            ->map(fn (Venue $venue) => $venue->only([
                'id',
                'name',
                'address',
                'tel',
                'fax',
                'website_url',
            ]))
            ->values();

        return response()->json($venues);
    }

    // 会場詳細API（大会create/edit用）
    public function showJson(int $id)
    {
        $venue = Venue::findOrFail($id, [
            'id',
            'name',
            'aliases',
            'address',
            'postal_code',
            'tel',
            'fax',
            'website_url',
            'note',
            'is_active',
        ]);

        return response()->json($venue);
    }

    // 新規
    public function create()
    {
        $venue = new Venue;

        return view('venues.create', compact('venue'));
    }

    // 保存
    public function store(Request $request, VenueNameNormalizer $normalizer)
    {
        $validated = $this->validatedData($request, $normalizer);

        Venue::create($validated);

        return redirect()->route('venues.index')->with('success', '会場を登録しました。');
    }

    // 編集
    public function edit($id)
    {
        $venue = Venue::findOrFail($id);

        return view('venues.edit', compact('venue'));
    }

    // 更新
    public function update(Request $request, $id, VenueNameNormalizer $normalizer)
    {
        $venue = Venue::findOrFail($id);
        $validated = $this->validatedData($request, $normalizer, $venue);

        $venue->update($validated);

        return redirect()->route('venues.index')->with('success', '会場を更新しました。');
    }

    // 削除
    public function destroy($id)
    {
        $venue = Venue::findOrFail($id);
        $venue->delete();

        return redirect()->route('venues.index')->with('success', '会場を削除しました。');
    }

    /**
     * DBの指定テーブルに必要なカラムが全て存在するか検査し、
     * 欠落があれば人間が気づけるようにバリデーションエラーを投げる。
     * （用語）search_path：PostgreSQLの検索スキーマ。これのズレで「列が無い」ように見えることがある。
     */
    private function assertColumnsExist(string $table, array $required): void
    {
        if (! Schema::hasTable($table)) {
            abort(500, "テーブル {$table} が見つかりません。接続先や search_path を確認してください。");
        }
        $cols = Schema::getColumnListing($table);
        $missing = array_values(array_diff($required, $cols));
        if (! empty($missing)) {
            // 人が読めるように日本語名も添える
            $labels = [
                'name' => '会場名', 'address' => '住所', 'postal_code' => '郵便番号',
                'tel' => 'TEL', 'fax' => 'FAX', 'website_url' => '公式サイト', 'note' => '会場データ',
                'canonical_key' => '会場識別キー', 'aliases' => '別名', 'is_active' => '現役状態',
            ];
            $human = array_map(fn ($k) => $labels[$k] ?? $k, $missing);
            $msg = 'DBに次の列がありません：'.implode(' / ', $human).'（テーブル: '.$table.'）';
            // バリデーションエラー扱いで戻す
            back()->withErrors(['db_columns' => $msg])->throwResponse();
        }
    }

    private function validatedData(
        Request $request,
        VenueNameNormalizer $normalizer,
        ?Venue $venue = null
    ): array {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'aliases_text' => 'nullable|string|max:2000',
            'address' => 'nullable|string|max:255',
            'postal_code' => ['nullable', 'regex:/^\d{3}-?\d{4}$/'],
            'tel' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $canonicalKey = $normalizer->normalize($validated['name']);
        $duplicate = Venue::query()
            ->when($venue, fn ($query) => $query->where('id', '<>', $venue->id))
            ->get(['id', 'name', 'canonical_key', 'aliases'])
            ->contains(function (Venue $candidate) use ($canonicalKey, $normalizer) {
                if ($candidate->canonical_key === $canonicalKey) {
                    return true;
                }

                return collect([$candidate->name, ...($candidate->aliases ?? [])])
                    ->contains(fn ($name) => $normalizer->normalize($name) === $canonicalKey);
            });

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => '表記を正規化すると同じ会場がすでに登録されています。既存会場を編集してください。',
            ]);
        }

        $aliases = preg_split('/[\r\n,、]+/u', (string) ($validated['aliases_text'] ?? '')) ?: [];
        unset($validated['aliases_text']);

        $validated['canonical_key'] = $canonicalKey;
        $validated['aliases'] = collect($aliases)
            ->map(fn ($alias) => trim($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $validated['is_active'] = $request->boolean('is_active');

        if (! empty($validated['postal_code'])) {
            $postalCode = preg_replace('/\D/', '', $validated['postal_code']);
            if (strlen($postalCode) === 7) {
                $validated['postal_code'] = substr($postalCode, 0, 3).'-'.substr($postalCode, 3);
            }
        }

        $this->assertColumnsExist('venues', [
            'name',
            'canonical_key',
            'aliases',
            'is_active',
            'address',
            'postal_code',
            'tel',
            'fax',
            'website_url',
            'note',
        ]);

        return $validated;
    }
}
