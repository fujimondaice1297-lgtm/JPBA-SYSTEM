<h2>褒章記録 詳細</h2>

<table border="1" cellpadding="10">
    <tr>
        <th>褒章種別</th>
        <td>
            @if ($recordType->award_type === 'perfect')
                公認パーフェクト
            @elseif ($recordType->award_type === 'seven_ten')
                公認7-10メイド
            @elseif ($recordType->award_type === 'eight_hundred')
                公認800シリーズ
            @endif
        </td>
    </tr>
    <tr>
        <th>選手名</th>
        <td>{{ $recordType->proBowler->name }}</td>
    </tr>
    <tr>
        <th>大会名</th>
        <td>{{ $recordType->tournament_name }}</td>
    </tr>
    <tr>
        <th>該当ゲーム数</th>
        <td>{{ $recordType->game_numbers }}</td>
    </tr>
    @if ($recordType->award_type === 'seven_ten')
        <tr>
            <th>該当フレーム数</th>
            <td>{{ $recordType->frame_number }}</td>
        </tr>
    @endif
    <tr>
        <th>日付</th>
        <td>{{ $recordType->awarded_on }}</td>
    </tr>
    <tr>
        <th>公認番号</th>
        <td>{{ $recordType->certification_number }}</td>
    </tr>
</table>

<a href="{{ route('record_types.edit', $recordType->id) }}">編集</a> |
    @if(auth()->user()?->isAdmin())
    <form action="{{ route('admin.record_types.destroy', $recordType->id) }}"
            method="POST" style="display:inline;">
        @csrf
        @method('DELETE')
        <button type="submit" onclick="return confirm('本当に削除しますか？')">削除</button>
    </form>
    @endif

<a href="{{ route('record_types.index') }}">← 一覧に戻る</a>
