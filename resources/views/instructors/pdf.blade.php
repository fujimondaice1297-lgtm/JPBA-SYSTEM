<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: ipag; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px; text-align: center; }
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
                <th>等級</th>
                <th>表示</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($instructors as $i)
                <tr>
                    <td>{{ $i->name }}</td>
                    <td>{{ $i->license_no }}</td>
                    <td>{{ $i->district->label ?? '-' }}</td>
                    <td>{{ $i->sex ? '男性' : '女性' }}</td>
                    <td>{{ $i->type_label }}</td>
                    <td>{{ $i->grade }}</td>
                    <td>{{ $i->is_visible ? '○' : '×' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
