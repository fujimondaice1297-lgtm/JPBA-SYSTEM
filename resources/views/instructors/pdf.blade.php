<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: ipaexg, ipag, sans-serif;
            font-size: 9px;
        }
        h3 {
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }
        .text-left {
            text-align: left;
        }
    </style>
</head>
<body>
    <h3>インストラクター一覧</h3>

    <table>
        <thead>
            <tr>
                <th>氏名</th>
                <th>識別番号</th>
                <th>種別</th>
                <th>取込元</th>
                <th>状態</th>
                <th>履歴理由</th>
                <th>地区</th>
                <th>性別</th>
                <th>区分</th>
                <th>更新年度</th>
                <th>更新期限</th>
                <th>更新状態</th>
                <th>更新日</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($instructors as $i)
                @php
                    $displayCode = $i->license_no
                        ?? $i->cert_no
                        ?? $i->legacy_instructor_license_no
                        ?? '-';

                    $sexLabel = $i->sex === null
                        ? '-'
                        : ($i->sex ? '男性' : '女性');
                @endphp
                <tr>
                    <td class="text-left">{{ $i->name }}</td>
                    <td>{{ $displayCode }}</td>
                    <td>{{ $i->type_label }}</td>
                    <td>{{ $i->source_type_label }}</td>
                    <td>{{ $i->current_state_label }}</td>
                    <td>{{ $i->supersede_reason_label }}</td>
                    <td>{{ $i->district->label ?? '-' }}</td>
                    <td>{{ $sexLabel }}</td>
                    <td>{{ $i->grade ?? '-' }}</td>
                    <td>{{ $i->renewal_year ?? '-' }}</td>
                    <td>{{ optional($i->renewal_due_on)->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $i->renewal_status_label }}</td>
                    <td>{{ optional($i->renewed_at)->format('Y-m-d') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13">該当データなし</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
