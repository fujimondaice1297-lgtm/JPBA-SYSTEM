@extends('layouts.app')

@section('content')
<main class="flex-fill px-4 py-4">
  <h3 class="fw-bold mb-4">インストラクター編集</h3>

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>入力内容に誤りがあります：</strong>
      <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('instructors.update', $instructor->license_no) }}">
    @csrf
    @method('PUT')

    <div class="row g-3">
      <div class="col-md-6">
        <label>ライセンスNo<span class="text-danger">*</span></label>
        <input type="text" name="license_no" class="form-control" value="{{ old('license_no', $instructor->license_no) }}" readonly>
      </div>
      <div class="col-md-6">
        <label>氏名<span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $instructor->name) }}" required>
      </div>
      <div class="col-md-6">
        <label>フリガナ</label>
        <input type="text" name="name_kana" class="form-control" value="{{ old('name_kana', $instructor->name_kana) }}">
      </div>
      <div class="col-md-6">
        <label>性別<span class="text-danger">*</span></label>
        <select name="sex" class="form-select" required>
            <option value="">選択してください</option>
            <option value="1" {{ old('sex', $instructor->sex ?? '') == '1' ? 'selected' : '' }}>男性</option>
            <option value="0" {{ old('sex', $instructor->sex ?? '') == '0' ? 'selected' : '' }}>女性</option>
        </select>
      </div>
      <div class="col-md-6">
        <label>地区</label>
        <select name="district_id" class="form-select">
          <option value="">選択してください</option>
          @foreach ($districts as $district)
            <option value="{{ $district->id }}" {{ old('district_id', $instructor->district_id) == $district->id ? 'selected' : '' }}>
              {{ $district->label }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6">
        <label>インストラクター種別<span class="text-danger">*</span></label>
        <select name="instructor_type" class="form-select" required>
          <option value="">選択してください</option>
          <option value="pro" {{ old('instructor_type', $instructor->instructor_type) == 'pro' ? 'selected' : '' }}>プロインストラクター</option>
          <option value="certified" {{ old('instructor_type', $instructor->instructor_type) == 'certified' ? 'selected' : '' }}>認定インストラクター</option>
        </select>
      </div>
      <div class="col-md-6">
        <label>資格等級<span class="text-danger">*</span></label>
        <select name="grade" class="form-select" required>
          <option value="">選択してください</option>
          @foreach (["C級", "準B級", "B級", "準A級", "A級", "2級", "1級"] as $grade)
            <option value="{{ $grade }}" {{ old('grade', $instructor->grade) == $grade ? 'selected' : '' }}>{{ $grade }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <input type="hidden" name="is_active" value="1">
    <input type="hidden" name="is_visible" value="1">
    <input type="hidden" name="coach_qualification" value="0">

    <div class="mt-4">
      <button type="submit" class="btn btn-primary">更新</button>
      <a href="{{ route('instructors.index') }}" class="btn btn-secondary">キャンセル</a>
    </div>
  </form>
</main>
@endsection
