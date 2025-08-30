@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">承認ボール 新規登録（最大10件）</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $brands = [
            'ABS', '900Global', 'Pro-am', 'MOTIV', 'HI-SP', 'STORM', 'ROTOGRIP',
            'Hammer', 'EBONITE', 'Track', 'Columbia300', 'Brunswick', 'Radical', 'DV8'
        ];
        $currentYear = now()->year;
        $years = range($currentYear, 1995);
    @endphp

    <form action="{{ route('approved_balls.store_multiple') }}" method="POST">
        @csrf

        @for ($i = 0; $i < 10; $i++)
            <div class="card mb-3">
                <div class="card-header">登録 {{ $i + 1 }}</div>
                <div class="card-body row g-3">
                    {{-- メーカー名 --}}
                    <div class="col-md-3">
                        <label>メーカー名</label>
                        <select name="balls[{{ $i }}][manufacturer]" class="form-control">
                            <option value="">選択してください</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand }}" {{ old("balls.$i.manufacturer") == $brand ? 'selected' : '' }}>
                                    {{ $brand }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ボール名（英語） --}}
                    <div class="col-md-3">
                        <label>ボール名（英語）</label>
                        <input type="text" name="balls[{{ $i }}][name]" class="form-control"
                            placeholder="例：Galaxy Black" value="{{ old("balls.$i.name") }}">
                    </div>

                    {{-- ボール名（カナ） --}}
                    <div class="col-md-3">
                        <label>ボール名（カナ）</label>
                        <input type="text" name="balls[{{ $i }}][name_kana]" class="form-control"
                            placeholder="例：ギャラクシーブラック" value="{{ old("balls.$i.name_kana") }}">
                    </div>

                    {{-- 発売年度 --}}
                    <div class="col-md-2">
                        <label>発売年度</label>
                        <select name="balls[{{ $i }}][release_year]" class="form-control">
                            <option value="">選択してください</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ old("balls.$i.release_year") == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 承認チェック --}}
                    <div class="col-md-1 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="balls[{{ $i }}][approved]"
                                value="1" {{ old("balls.$i.approved") ? 'checked' : '' }}>
                            <label class="form-check-label">承認</label>
                        </div>
                    </div>
                </div>
            </div>
        @endfor

        <div class="text-center">
            <button type="submit" class="btn btn-primary">登録</button>
            <a href="{{ route('approved_balls.index') }}" class="btn btn-secondary">ボール一覧へ戻る</a>
        </div>
    </form>
</div>
@endsection
