@extends('layouts.app')

@section('content')
<div class="container" style="max-width:900px">
  <h2 class="mb-3">お知らせ 編集（ID: {{ $information->id }}）</h2>
  @include('admin.informations.form')
</div>
@endsection