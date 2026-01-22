{{-- $html はサニタイズ済み前提の管理画面入力。最低限 nl2br する --}}
{!! str_contains($html,'<') ? $html : nl2br(e($html)) !!}
