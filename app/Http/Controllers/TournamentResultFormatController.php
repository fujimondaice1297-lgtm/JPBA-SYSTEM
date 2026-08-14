<?php

namespace App\Http\Controllers;

use App\Models\TournamentResultFormat;
use App\Models\TournamentResultFormatVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TournamentResultFormatController extends Controller
{
    public function index()
    {
        $formats = TournamentResultFormat::query()
            ->with('versions')
            ->orderBy('name')
            ->get();

        return view('tournament_result_formats.index', compact('formats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:tournament_result_formats,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'template' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $format = TournamentResultFormat::query()->create([
                'name' => $validated['name'],
                'code' => Str::lower($validated['code']),
                'description' => $validated['description'] ?? null,
                'is_active' => true,
            ]);

            $path = $request->file('template')->storeAs(
                'tournament_result_formats/'.$format->code,
                'v1.xlsx',
                'local'
            );

            $format->versions()->create([
                'version_no' => 1,
                'template_disk' => 'local',
                'template_path' => $path,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
            ]);
        });

        return redirect()
            ->route('tournament_result_formats.index')
            ->with('success', '最終成績フォーマットを登録しました。');
    }

    public function storeVersion(Request $request, TournamentResultFormat $format)
    {
        $validated = $request->validate([
            'template' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $nextVersion = ((int) $format->versions()->max('version_no')) + 1;
        $path = $request->file('template')->storeAs(
            'tournament_result_formats/'.$format->code,
            'v'.$nextVersion.'.xlsx',
            'local'
        );

        $format->versions()->create([
            'version_no' => $nextVersion,
            'template_disk' => 'local',
            'template_path' => $path,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('tournament_result_formats.index')
            ->with('success', 'Excelを第'.$nextVersion.'版として登録しました。既存大会は選択済みの版を引き続き使用します。');
    }

    public function download(TournamentResultFormatVersion $version)
    {
        $version->loadMissing('format');
        $name = sprintf(
            '%s_v%d.xlsx',
            $version->format?->code ?: 'result_format',
            $version->version_no
        );

        return response()->download($version->absoluteTemplatePath(), $name);
    }
}
