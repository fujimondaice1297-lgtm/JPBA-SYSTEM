@extends('layouts.app')

@section('content')
<main class="flex-fill px-4 py-4">
  <h3 class="fw-bold mb-4">インストラクター一覧データ</h3>

  @php
    $importSummary = session('auth_instructor_import_summary');
    $isAdmin = auth()->check() && auth()->user()->isAdmin();
  @endphp

  @if (session('success'))
    <div class="alert alert-success border mb-4">
      {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger border mb-4">
      {{ session('error') }}
    </div>
  @endif

  @if ($importSummary)
    <div class="alert alert-info border mb-4">
      <div class="fw-bold mb-2">認定インストラクターCSV取込サマリ（{{ $importSummary['target_year'] ?? '-' }}年）</div>
      <div class="row g-2 small">
        <div class="col-md-3">新規: <strong>{{ $importSummary['created'] ?? 0 }}</strong></div>
        <div class="col-md-3">更新: <strong>{{ $importSummary['updated'] ?? 0 }}</strong></div>
        <div class="col-md-3">スキップ: <strong>{{ $importSummary['skipped'] ?? 0 }}</strong></div>
        <div class="col-md-3">CSV未掲載で期限切れ化: <strong>{{ $importSummary['expired_missing'] ?? 0 }}</strong></div>
        <div class="col-md-3">license一致結線: <strong>{{ $importSummary['linked_by_license'] ?? 0 }}</strong></div>
        <div class="col-md-3">複合条件結線: <strong>{{ $importSummary['linked_by_composite'] ?? 0 }}</strong></div>
        <div class="col-md-3">未結線: <strong>{{ $importSummary['unlinked'] ?? 0 }}</strong></div>
        <div class="col-md-3">current更新済み認定: <strong>{{ $importSummary['renewed_current'] ?? 0 }}</strong></div>
        <div class="col-md-3">認定→プロボウラー履歴化: <strong>{{ $importSummary['promoted_to_pro_bowler'] ?? 0 }}</strong></div>
        <div class="col-md-3">認定→プロインストラクター履歴化: <strong>{{ $importSummary['promoted_to_pro_instructor'] ?? 0 }}</strong></div>
        <div class="col-md-3">取込元で無効: <strong>{{ $importSummary['inactive_in_source'] ?? 0 }}</strong></div>
      </div>
    </div>
  @endif

  <div class="mb-4">
    <div class="bg-secondary text-white px-3 py-2 fw-bold">検索条件</div>
    <div class="border px-4 py-4 bg-white">
      <form method="GET" action="{{ route('instructors.index') }}" class="mb-0">
        <div class="row g-3">
          <div class="col-md-3">
            <input type="text" name="name" class="form-control" placeholder="氏名（部分一致）" value="{{ request('name') }}">
          </div>

          <div class="col-md-3">
            <input type="text" name="license_no" class="form-control" placeholder="ライセンスNo. / 認定番号" value="{{ request('license_no') }}">
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
              <option value="retired" {{ request('instructor_class') === 'retired' ? 'selected' : '' }}>退会済み</option>
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

          <div class="col-md-3">
            <input type="number" name="renewal_year" class="form-control" placeholder="更新年度（例: {{ now()->year }})" value="{{ request('renewal_year') }}">
          </div>

          <div class="col-md-3">
            <select name="renewal_status" class="form-select">
              <option value="">更新状態を選択</option>
              @foreach ($renewalStatuses as $statusKey => $statusLabel)
                <option value="{{ $statusKey }}" {{ request('renewal_status') === $statusKey ? 'selected' : '' }}>
                  {{ $statusLabel }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <select name="source_type" class="form-select">
              <option value="">取込元を選択</option>
              @foreach ($sourceTypes as $sourceTypeKey => $sourceTypeLabel)
                <option value="{{ $sourceTypeKey }}" {{ request('source_type') === $sourceTypeKey ? 'selected' : '' }}>
                  {{ $sourceTypeLabel }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <select name="supersede_reason" class="form-select">
              <option value="">履歴理由を選択</option>
              @foreach ($supersedeReasons as $reasonKey => $reasonLabel)
                <option value="{{ $reasonKey }}" {{ request('supersede_reason') === $reasonKey ? 'selected' : '' }}>
                  {{ $reasonLabel }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <div class="form-check mt-2 pt-4">
              <input
                class="form-check-input"
                type="checkbox"
                name="unlinked_certified"
                value="1"
                id="unlinked_certified"
                {{ request()->boolean('unlinked_certified') ? 'checked' : '' }}
              >
              <label class="form-check-label" for="unlinked_certified">未結線認定のみ</label>
            </div>
          </div>

          <div class="col-12">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <button type="submit" class="btn btn-primary">検索</button>
              <a href="{{ route('instructors.index') }}" class="btn btn-warning">リセット</a>
              <a href="{{ route('instructors.create') }}" class="btn btn-success">新規登録</a>
              <a href="{{ route('instructors.import_auth_form') }}" class="btn btn-outline-primary">認定CSV取込</a>
              <a href="{{ route('athlete.index') }}" class="btn btn-secondary">インデックスへ戻る</a>
              <a href="{{ route('instructors.exportPdf', request()->query()) }}" class="btn btn-dark">PDF出力</a>

              <div class="form-check ms-2">
                <input
                  class="form-check-input"
                  type="checkbox"
                  name="include_history"
                  value="1"
                  id="include_history"
                  {{ request()->boolean('include_history') || request('instructor_class') === 'retired' || request('renewal_status') === 'expired' ? 'checked' : '' }}
                >
                <label class="form-check-label" for="include_history">履歴を含める</label>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="border rounded p-3 bg-light">
              <div class="fw-bold mb-2">更新専用一覧</div>
              <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('instructors.index', ['renewal_year' => now()->year, 'renewal_status' => 'pending']) }}" class="btn btn-outline-primary btn-sm">今年の更新対象</a>
                <a href="{{ route('instructors.index', ['renewal_year' => now()->year, 'renewal_status' => 'renewed']) }}" class="btn btn-outline-success btn-sm">今年の更新済み</a>
                <a href="{{ route('instructors.index', ['renewal_year' => now()->year, 'renewal_status' => 'expired', 'include_history' => 1]) }}" class="btn btn-outline-danger btn-sm">今年の期限切れ</a>
                <a href="{{ route('instructors.index', ['instructor_class' => 'certified_instructor', 'unlinked_certified' => 1]) }}" class="btn btn-outline-warning btn-sm">未結線認定</a>
                <a href="{{ route('instructors.index', ['source_type' => 'auth_instructor_csv']) }}" class="btn btn-outline-secondary btn-sm">認定CSV</a>
                <a href="{{ route('instructors.index', ['source_type' => 'pro_bowler_csv']) }}" class="btn btn-outline-secondary btn-sm">プロCSV</a>
              </div>
            </div>
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
              <th>識別番号</th>
              <th>種別</th>
              <th>結線先プロ</th>
              <th>取込元</th>
              <th>状態</th>
              <th>履歴理由</th>
              <th>地区</th>
              <th>性別</th>
              <th>区分</th>
              <th>更新年度</th>
              <th>更新期限</th>
              <th>更新状態</th>
              <th>更新日</th>
              <th>有効</th>
              <th>表示</th>
              <th>操作</th>
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
                  ? '-'
                  : ($instructor->sex ? '男性' : '女性');

                $nameUrl = route('instructors.edit', ['instructor' => $instructor->id]);

                $sourceTypeLabel = $sourceTypes[$instructor->source_type] ?? ($instructor->source_type ?? '-');

                $supersedeReasonLabel = $supersedeReasons[$instructor->supersede_reason] ?? ($instructor->supersede_reason ?: '-');

                $rowStateLabel = $instructor->is_current ? 'current' : 'history';
                $rowStateBadgeClass = $instructor->is_current ? 'bg-success' : 'bg-secondary';

                if ($instructor->proBowler) {
                  $linkedProLabel = ($instructor->proBowler->license_no ?? '-') . ' / ' . ($instructor->proBowler->name_kanji ?? '-');
                } elseif ($instructor->pro_bowler_id) {
                  $linkedProLabel = 'ID:' . $instructor->pro_bowler_id . ' / 未取得';
                } else {
                  $linkedProLabel = '-';
                }

                $canRetire = $isAdmin
                  && ($instructor->source_type ?? null) === 'manual'
                  && (bool) $instructor->is_current;
              @endphp
              <tr>
                <td>
                  <a href="{{ $nameUrl }}">{{ $instructor->name }}</a>
                </td>
                <td>{{ $displayCode }}</td>
                <td>{{ $instructor->type_label }}</td>
                <td>{{ $linkedProLabel }}</td>
                <td>{{ $sourceTypeLabel }}</td>
                <td><span class="badge {{ $rowStateBadgeClass }}">{{ $rowStateLabel }}</span></td>
                <td>{{ $supersedeReasonLabel }}</td>
                <td>{{ $instructor->district->label ?? '-' }}</td>
                <td>{{ $sexLabel }}</td>
                <td>{{ $instructor->grade ?? '-' }}</td>
                <td>{{ $instructor->renewal_year ?? '-' }}</td>
                <td>{{ optional($instructor->renewal_due_on)->format('Y-m-d') ?? '-' }}</td>
                <td>{{ $instructor->renewal_status_label }}</td>
                <td>{{ optional($instructor->renewed_at)->format('Y-m-d') ?? '-' }}</td>
                <td>{{ $instructor->is_active ? '○' : '×' }}</td>
                <td>{{ $instructor->is_visible ? '○' : '×' }}</td>
                <td>
                  @if ($canRetire)
                    <form method="POST" action="{{ route('admin.instructors.destroy', $instructor->id) }}" onsubmit="return confirm('このインストラクターを退会済みにします。物理削除はされません。よろしいですか？');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-outline-danger">退会</button>
                    </form>
                  @elseif (($instructor->source_type ?? null) !== 'manual')
                    <span class="text-muted small">同期元管理</span>
                  @else
                    <span class="text-muted small">履歴</span>
                  @endif
                </td>
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