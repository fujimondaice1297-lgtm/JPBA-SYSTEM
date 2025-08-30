@php
  // 表示用ヘルパ（空なら "—"）
  $S = fn($v) => isset($v) && $v !== '' ? $v : '—';
@endphp

<table class="table table-sm align-middle mb-0">
  <tbody>
    <tr><th class="w-25">氏名</th><td>{{ $S($b->name_kanji ?? $b->name) }}</td></tr>
    <tr><th>フリガナ</th><td>{{ $S($b->name_kana) }}</td></tr>
    <tr><th>性別</th><td>
      {{ ($b->sex ?? null) === 1 ? '男性' : (($b->sex ?? null) === 2 ? '女性' : '—') }}
    </td></tr>
    <tr><th>期別</th><td>{{ $b->kibetsu ? $b->kibetsu.'期' : '—' }}</td></tr>
    <tr><th>地区</th><td>{{ $S($b->district?->label ?? '—') }}</td></tr>

    <tr><th>所属先</th>
      <td>
        @if(!empty($b->organization_url))
          <a href="{{ $b->organization_url }}" target="_blank" rel="noopener">
            {{ $S($b->organization_name) }}
          </a>
        @else
            {{ $S($b->organization_name) }}
        @endif
      </td>
    </tr>

    <tr><th>公開住所</th>
      <td>
        {{ $S($b->public_zip) }}　
        {{ $S($b->public_addr1) }} {{ $S($b->public_addr2) }}
        @if(!empty($b->public_addr_same_as_org))
          <span class="badge bg-secondary ms-2">所属先と同じ</span>
        @endif
      </td>
    </tr>

    <tr><th>プロフィール写真</th>
      <td>{{ !empty($b->public_image_path) ? '登録あり' : '—' }}</td>
    </tr>

    <tr><th>生年月日（公開）</th>
      <td>
        @php
          $birth = $b->birthdate_public ?? null;
          if ($birth && ($b->birthdate_public_hide_year ?? false)) {
            try {
              $d = \Carbon\Carbon::parse($birth);
              $birth = $d->format('m月d日（年非表示）');
            } catch (\Throwable $e) {}
          }
        @endphp
        {{ $birth ? \Carbon\Carbon::parse($birth)->format('Y-m-d') : '—' }}
        @if($b->birthdate_public_is_private ?? false)
          <span class="badge bg-warning text-dark ms-1">非公表</span>
        @endif
      </td>
    </tr>

    <tr><th>会員種別</th><td>{{ $S($b->membership_type) }}</td></tr>

    <tr><th>SNS</th>
      <td class="small">
        @php
          $sns = [
            'Facebook' => $b->facebook ?? null,
            'Twitter'  => $b->twitter  ?? null,
            'Instagram'=> $b->instagram?? null,
            'Rankseeker'=> $b->rankseeker?? null,
          ];
          $has = false;
        @endphp
        @foreach($sns as $label => $url)
          @if($url)
            @php $has = true; @endphp
            <a class="me-2" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
          @endif
        @endforeach
        @unless($has) — @endunless
      </td>
    </tr>

    <tr><th>用品契約</th><td>{{ $S($b->equipment_contract) }}</td></tr>
    <tr><th>タイトル数</th><td>{{ method_exists($b,'titles') ? $b->titles->count() : '—' }}</td></tr>
  </tbody>
</table>
