<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: ipaexg, ipag, sans-serif;
            font-size: 11px;
        }
        h3 {
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <h3>インストラクター一覧</h3>

    <table>
        <thead>
            <tr>
                <th>氏名</th>
                <th>ライセンスNo.</th>
                <th>地区</th>
                <th>性別</th>
                <th>種別</th>
                <th>区分</th>
                <th>有効</th>
                <th>表示</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($instructors as $i)
                <tr>
                    <td>{{ $i->name }}</td>
                    <td>{{ $i->license_no }}</td>
                    <td>{{ $i->district->label ?? '-' }}</td>
                    <td>{{ $i->sex ? '男性' : '女性' }}</td>
                    <td>{{ $i->type_label ?? ($i->instructor_type === 'pro' ? 'プロ' : '認定') }}</td>
                    <td>{{ $i->grade ?? '-' }}</td>
                    <td>{{ $i->is_active ? '○' : '×' }}</td>
                    <td>{{ $i->is_visible ? '○' : '×' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">該当データなし</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>