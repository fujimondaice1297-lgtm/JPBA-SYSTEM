@extends('layouts.app')

@section('content')
<main class="flex-fill px-4 py-4">
  <h3 class="fw-bold mb-4">インストラクターデータ</h3>

  {{-- 検索フォーム --}}
  <div class="mb-4">
    <div class="bg-secondary text-white px-3 py-2 fw-bold">検索条件</div>
    <div class="border px-4 py-4 bg-white">
      <form method="GET" action="{{ route('instructors.index') }}" class="mb-4">
        <div class="row g-3">
          <div class="col-md-3">
            <input type="text" name="name" class="form-control" placeholder="例：山田 太郎" value="{{ request('name') }}">
          </div>
          <div class="col-md-3">
            <input type="text" name="license_no" class="form-control" placeholder="ライセンスNo." value="{{ request('license_no') }}">
          </div>
          <div class="col-md-3">
            <select name="district" class="form-select">
              <option value="">すべて地区</option>
              @foreach ($districts as $label)
                <option value="{{ $label }}" {{ request('district') == $label ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <select name="gender" class="form-select">
              <option value="">性別を選んでください</option>
              <option value="男性" {{ request('gender') == '男性' ? 'selected' : '' }}>男性</option>
              <option value="女性" {{ request('gender') == '女性' ? 'selected' : '' }}>女性</option>
            </select>
          </div>
          <div class="col-md-3">
            <select name="instructor_class" class="form-select">
              <option value="">種別選択</option>
              <option value="pro_bowler" {{ request('instructor_class') == 'pro_bowler' ? 'selected' : '' }}>プロボウラー</option>
              <option value="pro_instructor" {{ request('instructor_class') == 'pro_instructor' ? 'selected' : '' }}>プロイントラ</option>
              <option value="certified_instructor" {{ request('instructor_class') == 'certified_instructor' ? 'selected' : '' }}>認定イントラ</option>
            </select>
          </div>
          <div class="col-md-3">
            <select name="grade" class="form-select">
              <option value="">資格等級</option>
              @foreach (["C級", "準B級", "B級", "準A級", "A級", "2級", "1級"] as $grade)
                <option value="{{ $grade }}" {{ request('grade') == $grade ? 'selected' : '' }}>{{ $grade }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary">検索</button>
            <a href="{{ route('instructors.index') }}" class="btn btn-warning">リセット</a>
            <a href="{{ route('instructors.create') }}" class="btn btn-success">新規登録</a>
            <a href="#" class="btn btn-secondary">インデックスへ戻る</a>
            <a href="{{ route('instructors.exportPdf', request()->query()) }}" class="btn btn-dark">PDF出力</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- 一覧表示 --}}
  <div class="bg-white border p-4">
    @if ($instructors->count())
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>氏名</th>
            <th>ライセンスNo.</th>
            <th>地区</th>
            <th>性別</th>
            <th>種別</th>
            <th>等級</th>
            <th>表示</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($instructors as $instructor)
            <tr>
              <td>{{ $instructor->license_no }}</td>
              <td>
                @if ($instructor->instructor_type === 'pro' && $instructor->pro_bowler_id)
                  <a href="{{ route('pro_bowlers.edit', $instructor->pro_bowler_id) }}">
                    {{ $instructor->name }}
                  </a>
                @elseif ($instructor->instructor_type === 'certified')
                  <a href="{{ route('certified_instructors.edit', $instructor->license_no) }}">
                    {{ $instructor->name }}
                  </a>
                @else
                  <a href="{{ route('instructors.edit', $instructor->license_no) }}">
                    {{ $instructor->name }}
                  </a>
                @endif
              </td>
              <td>{{ $instructor->license_no }}</td>
              <td>{{ $instructor->district->label ?? '-' }}</td>
              <td>{{ $instructor->sex ? '男性' : '女性' }}</td>
              <td>{{ $instructor->type_label }}</td>
              <td>{{ $instructor->grade }}</td>
              <td>{{ $instructor->is_visible ? '○' : '×' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div>
        {{ $instructors->appends(request()->query())->links() }}
      </div>
    @else
      <p>該当するインストラクターデータが見つかりませんでした。</p>
    @endif
  </div>
</main>
@endsection
