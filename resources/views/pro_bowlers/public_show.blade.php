@extends('layouts.app')

@section('content')
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ request('return', url()->previous()) }}" class="btn btn-outline-secondary btn-sm">← 戻る</a>
    <span class="text-muted small">選手ID: {{ $view['id'] }}</span>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex gap-3">
        <div>
          @if($view['portrait'])
            <img src="{{ $view['portrait'] }}" class="rounded" style="width:140px;height:140px;object-fit:cover;" alt="">
          @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center"
                 style="width:140px;height:140px;">No Photo</div>
          @endif
        </div>
        <div class="flex-grow-1">
          <h2 class="mb-1">{{ $view['name'] }}</h2>
          @if($view['kana'])<div class="text-muted">{{ $view['kana'] }}</div>@endif

          <div class="row g-2 mt-2">
            <div class="col-sm-6 col-lg-3"><strong>ライセンスNo</strong>：<code>{{ $view['license_no'] ?: '—' }}</code></div>
            <div class="col-sm-6 col-lg-3"><strong>性別</strong>：{{ $view['sex'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>期別</strong>：{{ $view['kibetsu'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>地区</strong>：{{ $view['district'] }}</div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-sm-6 col-lg-3"><strong>生年月日</strong>：{{ $view['birth_public'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>出身地</strong>：{{ $view['birthplace'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>プロ入り</strong>：{{ $view['pro_entry_year'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>会員種別</strong>：{{ $view['membership_type'] }}</div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-sm-6 col-lg-3"><strong>身長</strong>：{{ $view['height'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>体重</strong>：{{ $view['weight'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>血液型</strong>：{{ $view['blood'] }}</div>
            <div class="col-sm-6 col-lg-3"><strong>利き腕</strong>：{{ $view['dominant_arm'] }}</div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-sm-6"><strong>A級番号</strong>：{{ $view['a_license_number'] }}</div>
            <div class="col-sm-6"><strong>永久シード取得</strong>：{{ $view['permanent_seed'] }}</div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-sm-6"><strong>殿堂入り</strong>：{{ $view['hall_of_fame'] }}</div>
            <div class="col-sm-6"><strong>地区長</strong>：{{ $view['is_district_leader'] ? '◯' : '—' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- 住所・所属先（公開） --}}
  <div class="card mb-4">
    <div class="card-header fw-bold">公開住所 / 所属先</div>
    <div class="card-body">
      <div class="row g-2">
        <div class="col-md-6">
          <div><strong>所属先名：</strong>{{ $view['organization']['name'] ?? '—' }}</div>
          <div><strong>所属先URL：</strong>
            @if(!empty($view['organization']['url']))
              <a href="{{ $view['organization']['url'] }}" target="_blank">{{ $view['organization']['url'] }}</a>
            @else — @endif
          </div>
        </div>
        <div class="col-md-6">
          <div class="small text-muted mb-1">
            （{{ $view['organization']['same_as_org'] ? '公開住所＝所属先' : '公開住所（個別）' }}）
          </div>
          <div><strong>〒</strong>{{ $view['organization']['zip'] ?? '—' }}</div>
          <div>{{ $view['organization']['addr1'] ?? '—' }} {{ $view['organization']['addr2'] ?? '' }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- 自己紹介系 --}}
  <div class="card mb-4">
    <div class="card-header fw-bold">プロフィール</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6"><strong>趣味・特技：</strong>{{ $view['hobby'] ?? '—' }}</div>
        <div class="col-md-6"><strong>ボウリング歴：</strong>{{ $view['bowling_history'] ?? '—' }}</div>
        <div class="col-md-6"><strong>他スポーツ歴：</strong>{{ $view['other_sports'] ?? '—' }}</div>
        <div class="col-md-6"><strong>今シーズン目標：</strong>{{ $view['season_goal'] ?? '—' }}</div>
        <div class="col-md-6"><strong>師匠・コーチ：</strong>{{ $view['coach'] ?? '—' }}</div>
        <div class="col-md-6"><strong>用品契約：</strong>{{ $view['equipment_contract'] ?? '—' }}</div>
        <div class="col-md-6"><strong>主な指導歴：</strong>{{ $view['coaching_history'] ?? '—' }}</div>
        <div class="col-md-6"><strong>座右の銘：</strong>{{ $view['motto'] ?? '—' }}</div>
        <div class="col-12"><strong>セールスポイント：</strong><br>{{ $view['selling_point'] ?? '—' }}</div>
        <div class="col-12"><strong>自由入力：</strong><br>{{ $view['free_comment'] ?? '—' }}</div>
      </div>
    </div>
  </div>

  {{-- スポンサー --}}
  <div class="card mb-4">
    <div class="card-header fw-bold">スポンサー</div>
    <div class="card-body">
      <div class="row g-2">
        @foreach($view['sponsors'] as $sp)
          <div class="col-md-4">
            <div><strong>{{ $sp['label'] }}</strong>：
              @if($sp['name'])
                {{ $sp['name'] }}
                @if($sp['url'])<span class="ms-1"><a href="{{ $sp['url'] }}" target="_blank">公式サイト</a></span>@endif
              @else — @endif
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- インストラクター／資格 --}}
  <div class="card mb-4">
    <div class="card-header fw-bold">インストラクター情報 / 資格</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>資格</th><th>有無</th><th>取得年月</th>
            </tr>
          </thead>
          <tbody>
            @foreach($view['instructors'] as $it)
              <tr>
                <td>{{ $it['label'] }}</td>
                <td>{{ $it['status'] }}</td>
                <td>{{ $it['year'] ?: '—' }}</td>
              </tr>
            @endforeach
            <tr>
              <td>USBCコーチ</td>
              <td colspan="2">{{ $view['usbc_coach'] }}</td>
            </tr>
            <tr>
              <td>JBC公認ドリラー資格</td>
              <td colspan="2">{{ $view['jbc_driller_cert'] }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- タイトル（簡易） --}}
  <div class="card mb-4">
    <div class="card-header fw-bold">タイトル</div>
    <div class="card-body">
      @if(($view['titles'] ?? collect())->count())
        <ul class="mb-0">
          @foreach($view['titles'] as $t)
            <li>{{ $t->year }}年 / {{ $t->title_name }} @if($t->won_date)（{{ \Carbon\Carbon::parse($t->won_date)->format('Y-m-d') }}）@endif</li>
          @endforeach
        </ul>
      @else
        <div class="text-muted">登録されたタイトルはありません。</div>
      @endif
    </div>
  </div>

  {{-- SNS / Links --}}
  <div class="card mb-4">
    <div class="card-header fw-bold">SNS / Link</div>
    <div class="card-body">
      @php $has = collect($view['sns'])->filter()->isNotEmpty(); @endphp
      @if($has)
        @foreach($view['sns'] as $label => $url)
          @if($url)
            <a class="btn btn-outline-primary btn-sm me-2 mb-2" target="_blank" href="{{ $url }}">{{ $label }}</a>
          @endif
        @endforeach
      @else
        <div class="text-muted">リンクは登録されていません。</div>
      @endif
    </div>
  </div>

</div>
@endsection
