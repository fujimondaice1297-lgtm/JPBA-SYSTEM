@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">公認800シリーズ判定設定</h2>
            <p class="text-muted mb-0">大会の対象シリーズを、正確に3ゲーム単位で定義します。未定義の任意3ゲームは判定しません。</p>
        </div>
        <a href="{{ route('record_types.index') }}" class="btn btn-outline-secondary">公認記録へ戻る</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label">大会で絞り込み</label>
                <select name="tournament_id" class="form-select">
                    <option value="">すべて</option>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected($tournamentId === $tournament->id)>
                            {{ optional($tournament->start_date)->format('Y/m/d') }} {{ $tournament->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary">表示</button></div>
        </div>
    </form>

    <div class="card mb-4">
        <div class="card-header">対象シリーズを追加</div>
        <div class="card-body">
            <form method="POST" action="{{ route('record_types.series_definitions.store') }}">
                @csrf
                @include('record_types._series_definition_fields', ['definition' => null])
                <button type="submit" class="btn btn-success mt-3">追加</button>
            </form>
        </div>
    </div>

    @forelse ($definitions as $definition)
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('record_types.series_definitions.update', $definition) }}">
                    @csrf
                    @method('PUT')
                    @include('record_types._series_definition_fields', ['definition' => $definition])
                    <button type="submit" class="btn btn-primary mt-3">更新</button>
                </form>
                <form method="POST" action="{{ route('record_types.series_definitions.destroy', $definition) }}" class="mt-2"
                      onsubmit="return confirm('このシリーズ設定を削除しますか？');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-light border text-muted">登録済みのシリーズ定義はありません。</div>
    @endforelse
</div>
@endsection
