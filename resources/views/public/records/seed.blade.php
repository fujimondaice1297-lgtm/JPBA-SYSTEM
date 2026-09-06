@extends('public.layout')
@section('title', 'トーナメントシード｜公益社団法人 日本プロボウリング協会')
@section('breadcrumb', 'トーナメントシード')
@section('content')
<h1 class="jpba-page-title">トーナメントシード</h1>
<section class="jpba-panel"><form method="GET" action="{{ route('public.records.seed') }}" class="row g-2 align-items-end"><div class="col-sm-4"><label class="form-label fw-bold">年度</label><select name="year" class="form-select">@foreach($availableYears as $year)<option value="{{ $year }}" @selected((int)$selectedYear===(int)$year)>{{ $year }}年</option>@endforeach</select></div><div class="col-sm-4"><label class="form-label fw-bold">性別</label><select name="gender" class="form-select"><option value="">男女すべて</option>@foreach($genderLabels as $code=>$label)<option value="{{ $code }}" @selected($selectedGender===$code)>{{ $label }}</option>@endforeach</select></div><div class="col-sm-4"><button class="btn btn-primary" type="submit">表示</button></div></form></section>
@php($visibleGenders=$selectedGender?[$selectedGender]:array_keys($genderLabels))
@foreach($visibleGenders as $genderCode)
  <section class="jpba-panel"><h2 class="jpba-section-title">{{ $selectedYear }}年 {{ $genderLabels[$genderCode] }}シードプロ</h2>
  @foreach(($sectionLabelsByGender[$genderCode] ?? []) as $sectionKey=>$sectionLabel)
    @php($rows=$sections[$genderCode][$sectionKey] ?? collect())
    <h3 class="h6 fw-bold mt-3">{{ $sectionLabel }}（{{ $rows->count() }}名）</h3>
    @if($rows->isEmpty())<p class="text-muted">登録はありません。</p>@else<div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead class="table-light"><tr><th>順位</th><th>ライセンスNo.</th><th>氏名</th><th>期</th><th>ポイント</th><th>獲得賞金</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ $row['seed_rank'] ?: '-' }}</td><td>{{ \App\Support\PublicLicenseNumber::format($row['display_license_no'] ?? $row['license_no']) }}</td><td>{{ $row['name_kanji'] }}</td><td>{{ $row['kibetsu'] }}</td><td>{{ $row['points'] }}</td><td>{{ $row['prize_money'] }}</td></tr>@endforeach</tbody></table></div>@endif
  @endforeach</section>
@endforeach
@endsection
