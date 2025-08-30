@extends('layouts.app')
@section('content')
<h2>プロボウラーCSVインポート</h2>
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
<form action="{{ route('pro_bowlers.import') }}" method="POST" enctype="multipart/form-data">
  @csrf
  <input type="file" name="csv" class="form-control w-auto d-inline-block" required>
  <button class="btn btn-success ms-2">取り込み</button>
</form>
@endsection
