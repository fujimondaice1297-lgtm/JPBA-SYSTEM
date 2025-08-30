@extends('layouts.app')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">講習一括登録（最大20名）</h2>
  </div>

      <div class="d-flex gap-2">
        {{-- ① レポート（抽出）ドロップダウン --}}
        <div class="btn-group">
          <a href="{{ route('trainings.reports') }}" class="btn btn-outline-primary">
            講習レポート
          </a>
          <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
                  data-bs-toggle="dropdown" aria-expanded="false">
            <span class="visually-hidden">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('trainings.reports', ['scope'=>'compliant']) }}">受講済（有効）</a></li>
            <li><a class="dropdown-item" href="{{ route('trainings.reports', ['scope'=>'missing']) }}">未受講</a></li>
            <li><a class="dropdown-item" href="{{ route('trainings.reports', ['scope'=>'expired']) }}">期限切れ</a></li>
            <li><a class="dropdown-item" href="{{ route('trainings.reports', ['scope'=>'expiring','days'=>365]) }}">残り1年以下</a></li>
          </ul>
        </div>

        {{-- ② インデックスへ戻る --}}
        <a href="{{ route('athlete.index') }}" class="btn btn-outline-secondary">
          インデックスへ戻る
        </a>
      </div>
    </div>
    {{-- ▲ ツールバーここまで --}}

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @if (session('bulk_detail'))
      <ul class="small text-muted">
        @foreach (session('bulk_detail') as $line)
          <li>{{ $line }}</li>
        @endforeach
      </ul>
    @endif
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
  @endif

  {{-- ▼ 共通受講日：右側に寄せる --}}
  <div class="d-flex justify-content-end align-items-end mb-3">
    <div class="d-flex align-items-end gap-2">
      <div>
        <label class="form-label mb-1">共通受講日</label>
        <input type="date" id="bulkDate" class="form-control" style="width: 180px;">
      </div>
      <button type="button" id="applyBulkDate" class="btn btn-outline-primary">
        入力済みの氏名行に一括適用
      </button>
    </div>
  </div>
  {{-- ▲ 共通受講日 右寄せ --}}

  <form method="POST" action="{{ route('trainings.bulk.store') }}">
    @csrf

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:180px">ライセンスNo</th>
            <th>氏名（自動表示）</th>
            <th style="width:180px">受講日</th>
          </tr>
        </thead>
        <tbody id="bulk-rows">
          @for ($i=0; $i<20; $i++)
            <tr>
              <td>
                <input type="text" class="form-control license"
                       name="rows[{{ $i }}][license_no]" placeholder="m0000123">
              </td>
              <td>
                <input type="text" class="form-control name" placeholder="— 自動表示 —" readonly>
              </td>
              <td>
                <input type="date" class="form-control"
                       name="rows[{{ $i }}][completed_at]">
              </td>
            </tr>
          @endfor
        </tbody>
      </table>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary">登録</button>
      <a href="{{ route('pro_bowlers.list') }}" class="btn btn-secondary">戻る</a>
    </div>
  </form>
</div>

{{-- 超軽量オート補完（既存のAPIを利用） --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  const rows = [...document.querySelectorAll('#bulk-rows tr')];
  rows.forEach(tr => {
    const lic = tr.querySelector('input.license');
    const name = tr.querySelector('input.name');

    lic.addEventListener('change', async () => {
      const v = (lic.value || '').trim();
      name.value = '';
      if (!v) return;

      try {
        // 既存のAPI: /api/pro-bowler-by-license/{licenseNo}
        const res = await fetch(`/api/pro-bowler-by-license/${encodeURIComponent(v)}`);
        if (!res.ok) throw new Error('not ok');
        const data = await res.json();
        name.value = data.name_kanji || '(不明)';
      } catch(e) {
        name.value = '(見つかりません)';
      }
    });
  });
});

const bulkBtn  = document.getElementById('applyBulkDate');
const bulkDate = document.getElementById('bulkDate');
if (bulkBtn) {
  bulkBtn.addEventListener('click', () => {
    const v = bulkDate.value;
    if (!v) return;
    document.querySelectorAll('#bulk-rows tr').forEach(tr => {
      const nameInput = tr.querySelector('input.name');
      const dateInput = tr.querySelector('input[name$="[completed_at]"]');
      if (nameInput && nameInput.value.trim() && dateInput) {
        dateInput.value = v;
      }
    });
  });
}

</script>
@endsection
