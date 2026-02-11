@extends('layouts.app')

@section('content')
<div class="container">

    {{-- ✅検索フォーム --}}
    <form method="GET" action="{{ route('pro_bowlers.index') }}" class="mb-4">
        <div class="row">
            <div class="col-md-6">
                <label class="form-label">名前</label>
                <input type="text" name="name" value="{{ request('name') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">ライセンスNo</label>
                <input type="text" name="license_no" value="{{ request('license_no') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">地区</label>
                <select name="district_id" class="form-select">
                    <option value="">すべて</option>
                    @foreach($districts as $id => $label)
                        <option value="{{ $id }}" {{ request('district_id') == $id ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 mt-3">
                <label class="form-label">性別</label>
                <select name="gender" class="form-select">
                    <option value="">すべて</option>
                    <option value="男性" {{ request('gender') == '男性' ? 'selected' : '' }}>男性</option>
                    <option value="女性" {{ request('gender') == '女性' ? 'selected' : '' }}>女性</option>
                </select>
            </div>

            <div class="col-md-3 mt-3">
                <select name="membership_type" class="form-select">
                    <option value="">すべて会員種別</option>
                    @foreach ($kaiinStatuses as $s)
                        <option value="{{ $s->name }}" {{ request("membership_type") == $s->name ? "selected" : "" }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mt-3 d-flex align-items-center">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="include_retired" id="include_retired" value="1" {{ request("include_retired") ? "checked" : "" }}>
                    <label class="form-check-label" for="include_retired">退会者も表示</label>
                </div>
            </div>

            <div class="col-md-3 mt-3">
                <label class="form-label">プロ入り年（From）</label>
                <input type="number" name="term_from" value="{{ request('term_from') }}" class="form-control">
            </div>
            <div class="col-md-3 mt-3">
                <label class="form-label">プロ入り年（To）</label>
                <input type="number" name="term_to" value="{{ request('term_to') }}" class="form-control">
            </div>

            <div class="col-md-3 mt-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">検索</button>
            </div>
        </div>
    </form>

    {{-- ✅一覧テーブル --}}
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ライセンスNo</th>
                <th>氏名</th>
                <th>地区</th>
                <th>性別</th>
                <th>プロ入り年</th>
                <th>会員種別</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bowlers as $bowler)
                <tr>
                    <td>{{ $bowler->license_no }}</td>
                    <td>{{ $bowler->name }}</td>
                    <td>{{ optional($bowler->district)->label }}</td>
                    <td>@if ($bowler->sex === 1) 男性 @elseif ($bowler->sex === 2) 女性 @else - @endif</td>
                    <td>{{ $bowler->pro_entry_year }}</td>
                    <td>{{ $bowler->membership_type }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">該当するプロボウラーがいません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ✅ページネーション --}}
    <div class="d-flex justify-content-center">
        {{ $bowlers->links() }}
    </div>

</div>
@endsection
