@extends('layouts.app')

@section('content')
  <h1>メール作成：{{ $group->name }}</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="POST" action="{{ route('pro_groups.mail.store',$group) }}" class="mb-4">
    @csrf
    <div class="mb-2 d-flex gap-2">
      <input class="form-control" style="max-width:320px" name="from_name"
             value="{{ old('from_name',$defaults['from_name']) }}" placeholder="送信者名">
      <input class="form-control" style="max-width:360px" type="email" name="from_address"
             value="{{ old('from_address',$defaults['from_address']) }}" placeholder="送信元メール">
    </div>

    <div class="mb-2">
      <input class="form-control" name="subject" value="{{ old('subject') }}" placeholder="件名" required>
    </div>

    <div class="mb-2">
      <textarea class="form-control" rows="12" name="body" placeholder="本文（{name} {license_no} {district} が差し込み可）" required>{{ old('body') }}</textarea>
      <div class="form-text">★差し込み: {name} / {license_no} / {district}</div>
    </div>

    <div class="d-flex gap-2">
      <input type="email" class="form-control" name="dry_run_to" style="max-width:320px"
             placeholder="テスト送信先（任意）">
      <button class="btn btn-outline-secondary" formaction="{{ route('pro_groups.mail.store',$group) }}">テスト送信</button>
      <button class="btn btn-primary">送信開始</button>
      <a class="btn btn-outline-primary" href="{{ route('pro_groups.show',$group) }}">グループ詳細へ戻る</a>
    </div>
  </form>
@endsection
