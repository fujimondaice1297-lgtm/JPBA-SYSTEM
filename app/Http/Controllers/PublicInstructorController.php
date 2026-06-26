<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Information;
use App\Models\InstructorRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicInstructorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'name' => trim((string) $request->query('name', '')),
            'license_no' => trim((string) $request->query('license_no', '')),
            'category' => trim((string) $request->query('category', '')),
            'grade' => trim((string) $request->query('grade', '')),
            'district_id' => trim((string) $request->query('district_id', '')),
        ];

        $query = InstructorRegistry::query()
            ->with(['district', 'proBowler'])
            ->where('is_current', true)
            ->where('is_active', true)
            ->where('is_visible', true);

        if ($filters['name'] !== '') {
            $name = $filters['name'];
            $query->where(function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%")
                    ->orWhere('name_kana', 'like', "%{$name}%");
            });
        }

        if ($filters['license_no'] !== '') {
            $keyword = $filters['license_no'];
            $query->where(function ($q) use ($keyword) {
                $like = "%{$keyword}%";
                $q->where('license_no', 'like', $like)
                    ->orWhere('legacy_instructor_license_no', 'like', $like)
                    ->orWhere('cert_no', 'like', $like);
            });
        }

        if (isset($this->categoryOptions()[$filters['category']])) {
            $query->where('instructor_category', $filters['category']);
        }

        if ($filters['grade'] !== '') {
            $query->where('grade', $filters['grade']);
        }

        if ($filters['district_id'] !== '' && ctype_digit($filters['district_id'])) {
            $query->where('district_id', (int) $filters['district_id']);
        }

        $instructors = $query
            ->orderBy('instructor_category')
            ->orderByRaw('district_id asc nulls last')
            ->orderByRaw('license_no asc nulls last')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('public.instructors.index', [
            'publicConfig' => config('jpba_public', []),
            'instructorConfig' => config('jpba_public.instructor', []),
            'filters' => $filters,
            'instructors' => $instructors,
            'districts' => District::query()->orderBy('id')->get(['id', 'label']),
            'categoryOptions' => $this->categoryOptions(),
            'gradeOptions' => $this->gradeOptions(),
            'categoryCounts' => $this->categoryCounts(),
            'instructorInformations' => $this->instructorInformations(),
        ]);
    }

    private function categoryOptions(): array
    {
        return [
            'pro_bowler' => 'プロボウラー',
            'pro_instructor' => 'プロ・インストラクター',
            'certified' => '認定インストラクター',
        ];
    }

    private function gradeOptions(): array
    {
        return ['A級', '準A級', 'B級', '準B級', 'C級', '1級', '2級'];
    }

    private function categoryCounts()
    {
        return InstructorRegistry::query()
            ->where('is_current', true)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->selectRaw('instructor_category, count(*) as count')
            ->groupBy('instructor_category')
            ->pluck('count', 'instructor_category');
    }

    private function instructorInformations()
    {
        return Information::active()
            ->public()
            ->where('category', 'ｲﾝｽﾄﾗｸﾀｰ')
            ->latest('published_at')
            ->latest('id')
            ->limit(5)
            ->get();
    }
}
