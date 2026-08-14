@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-3">公認記録の手動登録</h2>

    @include('record_types._form', [
        'action' => route('record_types.store'),
        'method' => 'POST',
        'recordType' => null,
    ])
</div>
@endsection
