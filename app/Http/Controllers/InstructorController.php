<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Instructor;
use App\Models\District;
use App\Models\ProBowler;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $query = Instructor::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('license_no')) {
            $query->where('license_no', $request->license_no);
        }

        if ($request->filled('district')) {
            $query->whereHas('district', function ($q) use ($request) {
                $q->where('label', $request->district);
            });
        }

        if ($request->filled('sex')) {
            $query->where('sex', $request->sex);
        }

        if ($request->filled('instructor_class')) {
            switch ($request->instructor_class) {
                case 'pro_bowler':
                    $query->proBowler();
                    break;
                case 'pro_instructor':
                    $query->proInstructor();
                    break;
                case 'certified_instructor':
                    $query->certifiedInstructor();
                    break;
            }
        }

        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        $instructors = $query->paginate(20);
        $districts = District::all();
        return view('instructors.index', compact('instructors', 'districts'));
    }

    public function create()
    {
        $districts = District::all();
        return view('instructors.create', compact('districts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'license_no' => 'required|unique:instructors,license_no',
            'name' => 'required|string',
            'name_kana' => 'nullable|string', 
            'sex' => 'required|boolean',
            'district_id' => 'nullable|exists:districts,id',
            'instructor_type' => 'required|in:pro,certified',
            'grade' => 'required|string',
        ]);

        $instructor = new Instructor($validated);

        if ($validated['instructor_type'] === 'pro') {
            $pro = ProBowler::where('license_no', $validated['license_no'])->first();
            if ($pro) {
                $instructor->pro_bowler_id = $pro->id;
            }
        }

        $instructor->save();

        return redirect()->route('instructors.index')->with('success', 'インストラクターを登録しました');
    }

    public function edit($licenseNo)
    {
        $instructor = Instructor::where('license_no', $licenseNo)->firstOrFail();
        $districts = District::all();

        return view('instructors.edit', compact('instructor', 'districts'));
    }

    public function update(Request $request, $licenseNo)
    {
        $instructor = Instructor::where('license_no', $licenseNo)->firstOrFail();

        $instructor->update($request->only([
            'name',
            'name_kana',
            'sex',
            'district_id',
            'instructor_type',
            'grade',
        ]));

        return redirect()->route('instructors.index')->with('success', '更新完了');
    }

    public function exportPdf(Request $request)
    {
        // 検索処理はそのまま
        $query = Instructor::query()->with('district');
        // ...検索条件略...
        $instructors = $query->get();

        // Dompdf直書き版
        $options = new Options();
        $options->set('defaultFont', 'ipaexg');
        $dompdf = new Dompdf($options);

        $html = view('instructors.pdf', compact('instructors'))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="instructors.pdf"');
    }


}
