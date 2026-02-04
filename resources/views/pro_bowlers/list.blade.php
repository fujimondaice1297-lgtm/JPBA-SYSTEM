@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">選手データ</h2>

    @php
      use App\Models\District;

      // 現在の検索クエリ付きURL（ページ番号含む）を“戻り先”として持たせる
      $returnUrl = request()->fullUrl();

      // Controllerが $bowlers / $proBowlers のどちらで渡しても動くように吸収
      $bowlers = $bowlers ?? ($proBowlers ?? null);

      // district options（Controllerから渡されない場合でもDBから取る）
      // 期待：id => label
      $districtOptions = [];
      if (isset($districts) && is_iterable($districts)) {
          // 1) id => label の配列/Collectionを想定
          foreach ($districts as $k => $v) {
              if (is_object($v) && isset($v->id)) {
                  $districtOptions[$v->id] = $v->label ?? ($v->name ?? (string)$v->id);
              } elseif (!is_numeric($k)) {
                  $districtOptions[$k] = $v;
              }
          }
      }
      if (empty($districtOptions)) {
          try {
              $districtOptions = District::query()->orderBy('id')->pluck('label', 'id')->toArray();
          } catch (\Throwable $e) {
              $districtOptions = [];
          }
      }

      // JPBA式 No.範囲（新旧パラメータ互換）
      $idStart = request('id_start', request('id_from'));
      $idEnd   = request('id_end', request('id_to'));

      // district_id（新）優先、district（旧：ラベル）互換
      $districtSelected = request('district_id');
      if (($districtSelected === null || $districtSelected === '') && request('district')) {
          $found = array_search(request('district'), $districtOptions, true);
          if ($found !== false) $districtSelected = (string)$found;
      }
      $districtLabelForHidden = ($districtSelected !== null && $districtSelected !== '' && isset($districtOptions[$districtSelected]))
          ? $districtOptions[$districtSelected]
          : (string)request('district', '');
    @endphp

    <!-- 検索フォーム -->
    <form method="GET" action="{{ route('pro_bowlers.list') }}" class="mb-4">
        <div class="row g-3">

            <div class="col-md-3">
                <input type="text" name="name" class="form-control" placeholder="例：山田 太郎"
                       value="{{ request('name') }}">
            </div>

            <div class="col-md-2">
                <input type="text" name="license_no" class="form-control" placeholder="ライセンスNo（部分一致）"
                       value="{{ request('license_no') }}">
            </div>

            <div class="col-md-2">
                <input type="text" name="id_start" class="form-control" placeholder="No.（開始）"
                       value="{{ $idStart }}">
                <small class="text-muted">※英字Noは片方だけ入力</small>

                {{-- 旧パラメータ互換（古いControllerでも動かす） --}}
                <input type="hidden" name="id_from" value="{{ $idStart }}">
            </div>

            <div class="col-md-2">
                <input type="text" name="id_end" class="form-control" placeholder="No.（終了）"
                       value="{{ $idEnd }}">
                <small class="text-muted">&nbsp;</small>

                {{-- 旧パラメータ互換（古いControllerでも動かす） --}}
                <input type="hidden" name="id_to" value="{{ $idEnd }}">
            </div>

            <div class="col-md-2">
                <select name="district_id" class="form-select">
                    <option value="">すべて地区</option>
                    @foreach ($districtOptions as $id => $label)
                        <option value="{{ $id }}" {{ (string)$districtSelected === (string)$id ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                {{-- 旧パラメータ互換：district(label) を hidden で送る --}}
                <input type="hidden" name="district" value="{{ $districtLabelForHidden }}">
            </div>

            <div class="col-md-2">
                <select name="gender" class="form-select">
                    <option value="">性別を選んでください</option>
                    <option value="男性" {{ request('gender')==='男性' ? 'selected' : '' }}>男性</option>
                    <option value="女性" {{ request('gender')==='女性' ? 'selected' : '' }}>女性</option>
                </select>
            </div>

            <div class="col-md-2">
                <input type="number" name="age_from" class="form-control" placeholder="年齢（開始）"
                       value="{{ request('age_from') }}">
            </div>
            <div class="col-md-2">
                <input type="number" name="age_to" class="form-control" placeholder="年齢（終了）"
                       value="{{ request('age_to') }}">
            </div>

            <div class="col-md-12">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="has_title" value="1"
                           {{ request()->boolean('has_title') ? 'checked' : '' }}>
                    <label class="form-check-label">タイトル保有者</label>
                </div>

                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="is_district_leader" value="1"
                           {{ request()->boolean('is_district_leader') ? 'checked' : '' }}>
                    <label class="form-check-label">地区長</label>
                </div>

                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="has_sports_coach_license" value="1"
                           {{ request()->boolean('has_sports_coach_license') ? 'checked' : '' }}>
                    <label class="form-check-label">スポーツコーチ</label>
                </div>

                <div class="form-check form-check-inline" style="min-width:260px;">
                    <input type="text" name="coach_name" class="form-control" placeholder="コーチ資格名（部分一致）"
                           value="{{ request('coach_name') }}">
                </div>

                <div class="form-check form-check-inline ms-2">
                    <input class="form-check-input" type="checkbox" name="include_inactive" value="1"
                           {{ request()->boolean('include_inactive') ? 'checked' : '' }}>
                    <label class="form-check-label">退会者も含む</label>
                </div>
            </div>

            <div class="col-md-12 d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary">検索</button>
                <a href="{{ route('pro_bowlers.list') }}" class="btn btn-warning">リセット</a>
                <a href="{{ route('pro_bowlers.create') }}?return={{ urlencode($returnUrl) }}" class="btn btn-success">新規登録</a>
                <a href="{{ route('pro_bowlers.import_form') }}" class="btn btn-outline-success">CSVインポート</a>
                <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
            </div>

        </div>
    </form>

    <!-- データテーブル -->
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ライセンスNo.</th>
                    <th>氏名</th>
                    <th>地区</th>
                    <th>性別</th>
                    <th>期別</th>
                    <th>タイトル</th>
                    <th>褒章</th>
                    <th>地区長</th>
                    <th>インストラクター級</th>
                    <th>スポーツコーチ</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bowlers ?? [] as $bowler)
                    @php
                        $editUrl = route('pro_bowlers.edit', $bowler->id) . '?return=' . urlencode($returnUrl);
                    @endphp
                    <tr data-id="{{ $bowler->id }}">
                        {{-- ライセンスNo. --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ $bowler->license_no ?? '-' }}
                            </a>
                        </td>

                        {{-- 氏名 --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ $bowler->name_kanji ?? '-' }}
                            </a>
                        </td>

                        {{-- 地区 --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ $bowler->district->label ?? '-' }}
                            </a>
                        </td>

                        {{-- 性別 --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                @if ($bowler->sex === 1) 男性
                                @elseif ($bowler->sex === 2) 女性
                                @else - @endif
                            </a>
                        </td>

                        {{-- 期別 --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ $bowler->kibetsu ? $bowler->kibetsu.'期' : '-' }}
                            </a>
                        </td>

                        {{-- タイトル保有数 --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ ($bowler->titles_count ?? 0) > 0 ? $bowler->titles_count : '無' }}
                            </a>
                        </td>

                        {{-- 褒章 --}}
                        <td>
                            <span class="badge bg-primary me-1">P: {{ $bowler->perfect_count ?? 0 }}</span>
                            <span class="badge bg-success me-1">7-10: {{ $bowler->seven_ten_count ?? 0 }}</span>
                            <span class="badge bg-info text-dark me-1">800: {{ $bowler->eight_hundred_count ?? 0 }}</span>
                            <span class="badge bg-secondary">
                                合計:
                                {{ ($bowler->perfect_count ?? 0) + ($bowler->seven_ten_count ?? 0) + ($bowler->eight_hundred_count ?? 0) }}
                            </span>
                        </td>

                        {{-- 地区長 --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ $bowler->is_district_leader ? '◯' : '-' }}
                            </a>
                        </td>

                        {{-- インストラクター級 --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ $bowler->instructor_grade_label ?? '-' }}
                            </a>
                        </td>

                        {{-- スポーツコーチ --}}
                        <td>
                            <a href="{{ $editUrl }}" class="text-decoration-none text-dark">
                                {{ $bowler->sports_coach_label ?? '-' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center">データがありません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ページネーション（検索条件を維持） -->
    <div class="mt-3">
        @if ($bowlers)
            {{ $bowlers->appends(request()->query())->links() }}
        @endif
    </div>
</div>
@endsection
