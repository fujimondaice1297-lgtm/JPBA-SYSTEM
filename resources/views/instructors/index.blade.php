@extends('layouts.app')

@section('content')
<main class="flex-fill px-4 py-4">
  <h3 class="fw-bold mb-4">インストラクター一覧データ</h3>

  <div class="mb-4">
    <div class="bg-secondary text-white px-3 py-2 fw-bold">検索条件</div>
    <div class="border px-4 py-4 bg-white">
      <form method="GET" action="{{ route('instructors.index') }}" class="mb-0">
        <div class="row g-3">
          <div class="col-md-3">
            <input
              type="text"
              name="name"
              class="form-control"
              placeholder="氏名（部分一致）"
              value="{{ request('name') }}"
            >
          </div>

          <div class="col-md-3">
            <input
              type="text"
              name="license_no"
              class="form-control"
              placeholder="ライセンスNo. / 認定番号"
              value="{{ request('license_no') }}"
            >
          </div>

          <div class="col-md-3">
            <select name="district_id" class="form-select">
              <option value="">すべての地区</option>
              @foreach ($districts as $district)
                <option value="{{ $district->id }}" {{ (string) request('district_id') === (string) $district->id ? 'selected' : '' }}>
                  {{ $district->label }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <select name="sex" class="form-select">
              <option value="">性別を選んでください</option>
              <option value="1" {{ request('sex') === '1' ? 'selected' : '' }}>男性</option>
              <option value="0" {{ request('sex') === '0' ? 'selected' : '' }}>女性</option>
            </select>
          </div>

          <div class="col-md-3">
            <select name="instructor_class" class="form-select">
              <option value="">種別を選択</option>
              <option value="pro_bowler" {{ request('instructor_class') === 'pro_bowler' ? 'selected' : '' }}>プロボウラー</option>
              <option value="pro_instructor" {{ request('instructor_class') === 'pro_instructor' ? 'selected' : '' }}>プロインストラクター</option>
              <option value="certified_instructor" {{ request('instructor_class') === 'certified_instructor' ? 'selected' : '' }}>認定インストラクター</option>
            </select>
          </div>

          <div class="col-md-3">
            <select name="grade" class="form-select">
              <option value="">区分を選択</option>
              @foreach ($grades as $grade)
                <option value="{{ $grade }}" {{ request('grade') === $grade ? 'selected' : '' }}>
                  {{ $grade }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6 d-flex align-items-center gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">検索</button>
            <a href="{{ route('instructors.index') }}" class="btn btn-warning">リセット</a>
            <a href="{{ route('instructors.create') }}" class="btn btn-success">新規登録</a>
            <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
            <a href="{{ route('instructors.exportPdf', request()->query()) }}" class="btn btn-dark">PDF出力</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="bg-white border p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="fw-bold">一覧表示</div>
      <div class="text-muted">全 {{ $instructors->total() }} 件</div>
    </div>

    @if ($instructors->count())
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead>
            <tr>
              <th>氏名</th>
              <th>ライセンスNo.</th>
              <th>地区</th>
              <th>性別</th>
              <th>種別</th>
              <th>区分</th>
              <th>有効</th>
              <th>表示</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($instructors as $instructor)
              @php
                $displayCode = $instructor->license_no
                  ?? $instructor->cert_no
                  ?? $instructor->legacy_instructor_license_no
                  ?? '-';

                $sexLabel = $instructor->sex === null
                  ? '—'
                  : ($instructor->sex ? '男性' : '女性');
              @endphp
              <tr>
                <td>
                  @if ($instructor->pro_bowler_id)
                    <a href="{{ route('pro_bowlers.edit', $instructor->pro_bowler_id) }}">
                      {{ $instructor->name }}
                    </a>
                  @elseif ($instructor->legacy_instructor_license_no)
                    <a href="{{ route('instructors.edit_by_license', ['license_no' => $instructor->legacy_instructor_license_no]) }}">
                      {{ $instructor->name }}
                    </a>
                  @else
                    {{ $instructor->name }}
                  @endif
                </td>
                <td>{{ $displayCode }}</td>
                <td>{{ $instructor->district->label ?? '-' }}</td>
                <td>{{ $sexLabel }}</td>
                <td>{{ $instructor->type_label }}</td>
                <td>{{ $instructor->grade ?? '-' }}</td>
                <td>{{ $instructor->is_active ? '○' : '×' }}</td>
                <td>{{ $instructor->is_visible ? '○' : '×' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div>
        {{ $instructors->appends(request()->query())->links() }}
      </div>
    @else
      <p class="mb-0">該当するインストラクターデータは見つかりませんでした。</p>
    @endif
  </div>
</main>
@endsection