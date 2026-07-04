<style>
        @page {
            margin: 15px 20px;
        }

        body,
        table,
        th,
        td,
        h1,
        h2,
        h3,
        div,
        span,
        p {
            font-family: ipaexg, sans-serif;
            font-weight: normal !important;
        }

        body {
            font-size: 10px;
            color: #000;
        }

        .nowrap {
            white-space: nowrap;
            word-break: keep-all;
            overflow-wrap: normal;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .jpba-heavy {
            text-shadow:
                0.45px 0 0 #000,
                0 0.45px 0 #000,
                0.45px 0.45px 0 #000;
        }

        .jpba-extra-heavy {
            text-shadow:
                0.65px 0 0 #000,
                0 0.65px 0 #000,
                0.65px 0.65px 0 #000,
                -0.35px 0 0 #000,
                0 -0.35px 0 #000;
        }

        .official-result-page {
            page-break-after: auto;
        }

        .official-top-title {
            text-align: center;
            line-height: 1.16;
            margin: 15px 0 12px 0;
        }

        .official-logo-wrap {
            height: 46px;
            margin: 0 auto 3px auto;
            text-align: center;
        }

        .official-logo-image {
            display: inline-block;
            width: 55px;
            height: auto;
            max-height: 44px;
        }

        .official-logo-text {
            display: inline-block;
            font-size: 14px;
            letter-spacing: 1px;
            border: 1px solid #111;
            padding: 2px 7px;
            line-height: 1.1;
        }

        .official-title-line-1 {
            font-size: 26px;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        .official-title-line-2 {
            font-size: 34px;
            letter-spacing: 5.5px;
            margin-top: 5px;
        }

        .official-title-line-3 {
            font-size: 32px;
            letter-spacing: 2px;
            margin-top: 4px;
        }

        .official-title-line-4 {
            font-size: 27px;
            letter-spacing: 1px;
            margin-top: 15px;
        }

        .official-title-line-5 {
            font-size: 31px;
            letter-spacing: 2px;
            margin-top: 5px;
        }

        .official-info {
            width: 82%;
            margin: 19px auto 16px auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12.3px;
            line-height: 1.38;
        }

        .official-info th,
        .official-info td {
            border: none;
            padding: 2px 4px;
            vertical-align: top;
        }

        .official-info th {
            width: 15%;
            text-align: right;
            white-space: nowrap;
        }

        .official-info td {
            text-align: left;
        }

        .official-info .left-info {
            width: 47%;
        }

        .official-info .right-info {
            width: 53%;
        }

        .official-competition-note {
            width: 82%;
            margin: 0 auto 14px auto;
            font-size: 12.2px;
            line-height: 1.55;
        }

        .official-competition-note-row {
            margin: 0;
            padding: 0;
        }

        .official-competition-label {
            display: inline-block;
            width: 98px;
            text-align: right;
            margin-right: 18px;
        }

        .official-prize-title {
            width: 86%;
            margin: 14px auto 8px auto;
            text-align: center;
            font-size: 26px;
            letter-spacing: 10px;
        }

        .official-prize-table {
            width: 88%;
            margin: 0 auto 9px auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12.4px;
        }

        .official-prize-table th,
        .official-prize-table td {
            border: 2px solid #111;
            padding: 5px 4px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.18;
            background: #fff;
        }

        .official-prize-table th {
            font-size: 11.4px;
            line-height: 1.14;
        }

        .official-prize-table .rank-col { width: 10.5%; }
        .official-prize-table .license-col { width: 10%; }
        .official-prize-table .license-cell {
            text-align: right;
            white-space: nowrap;
            padding-right: 8px;
            padding-left: 3px;
            letter-spacing: 0.2px;
        }
        .official-prize-table .name-col { width: 16%; }
        .official-prize-table .period-col { width: 5%; }
        .official-prize-table .belong-col { width: 25.5%; }
        .official-prize-table .total-point-col { width: 9.5%; }
        .official-prize-table .award-point-col { width: 7.5%; }
        .official-prize-table .step-point-col { width: 7.5%; }
        .official-prize-table .prize-col { width: 8.5%; }

        .official-prize-table .belong-cell {
            padding: 2px 3px;
            white-space: nowrap;
            overflow: hidden;
        }

        .official-prize-table .belong-text {
            display: block;
            width: 100%;
            line-height: 1.05;
            text-align: center;
            white-space: nowrap;
            letter-spacing: 0;
        }

        .official-prize-table .belong-text-size-1 { font-size: 10.8px; }
        .official-prize-table .belong-text-size-2 { font-size: 9.8px; }
        .official-prize-table .belong-text-size-3 { font-size: 8.8px; }
        .official-prize-table .belong-text-size-4 { font-size: 7.8px; }
        .official-prize-table .belong-text-size-5 { font-size: 6.9px; }
        .official-prize-table .belong-text-size-6 { font-size: 6.1px; }
        .official-prize-table .belong-text-size-7 { font-size: 5.4px; }
        .official-prize-table .belong-text-size-8 { font-size: 4.8px; }

        .official-record-box {
            width: 88%;
            margin: 10px auto 0 auto;
            font-size: 11.6px;
            line-height: 1.42;
        }

        .official-record-title {
            margin-bottom: 3px;
            font-size: 12px;
        }

        .official-borderless-note {
            width: 88%;
            margin: 7px auto 0 auto;
            font-size: 10.8px;
            line-height: 1.35;
        }

        .official-shootout-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-bracket-title {
            text-align: center;
            line-height: 1.06;
            margin: 34px 0 16px 0;
        }

        .official-bracket-title-line-1 {
            font-size: 29px;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .official-bracket-title-line-2 {
            font-size: 28px;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }

        .official-bracket-title-line-3 {
            font-size: 24px;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .official-bracket-title-line-4 {
            font-size: 25px;
            letter-spacing: 1px;
            margin-top: 26px;
            text-align: left;
            width: 74%;
            margin-left: auto;
            margin-right: auto;
            border-bottom: 2px solid #111;
            padding-bottom: 4px;
        }

        .official-bracket-rule-block {
            width: 74%;
            margin: 6px auto 18px auto;
            font-size: 13.5px;
            line-height: 1.55;
        }

        .official-bracket-rule-row {
            margin: 0 0 2px 0;
        }

        .official-bracket-wrap {
            width: 100%;
            text-align: center;
            margin: 0 0 8px 0;
        }

        .official-bracket-image {
            width: 87%;
            max-width: 87%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .official-score-section {
            margin: 4px 0 0 0;
            page-break-inside: avoid;
        }

        .official-score-section .official-next-score-block {
            margin-bottom: 0;
        }

        .official-score-heading {
            margin: 4px 0 3px 56px;
            line-height: 1.15;
            white-space: nowrap;
        }

        .official-score-logo {
            display: inline-block;
            width: 34px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border: 1px solid #222;
            border-radius: 2px;
            font-size: 8px;
            vertical-align: middle;
            margin-right: 5px;
        }

        .official-score-logo-image {
            display: inline-block;
            width: 36px;
            height: auto;
            max-height: 23px;
            margin-right: 6px;
            vertical-align: middle;
        }

        .official-score-title {
            display: inline-block;
            font-size: 17px;
            vertical-align: middle;
        }

        .official-score-meta {
            width: 86%;
            margin: 0 auto 4px auto;
            padding: 0 2px 3px 2px;
            border-bottom: 1px solid #222;
            font-size: 9.8px;
            line-height: 1.2;
            text-align: left;
        }

        .official-score-image-frame {
            width: 87%;
            margin: 0 auto 4px auto;
            padding: 3px 4px;
            border: 1px solid #111;
        }

        .official-score-image {
            width: 100%;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .official-next-score-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-next-score-main-title {
            margin: 22px 0 18px 0;
            text-align: center;
            font-size: 21px;
            line-height: 1.4;
        }

        .official-next-score-block {
            margin: 0 0 14px 0;
            page-break-inside: avoid;
        }

        .official-next-score-block + .official-next-score-block {
            margin-top: 10px;
            padding-top: 9px;
            border-top: 1px solid #777;
        }

        .official-next-score-heading {
            margin: 0 0 4px 56px;
            line-height: 1.15;
            white-space: nowrap;
        }

        .official-next-score-title {
            display: inline-block;
            font-size: 16px;
            vertical-align: middle;
        }

        .official-plain-score-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-plain-score-title {
            margin: 0 0 8px 0;
            text-align: center;
            font-size: 18px;
            line-height: 1.35;
        }


        .official-snapshot-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-snapshot-title {
            margin: 10px 0 2px 0;
            text-align: center;
            font-size: 20px;
            line-height: 1.05;
        }

        .official-snapshot-main {
            margin: 0;
            text-align: center;
            font-size: 20px;
            line-height: 1.08;
        }

        .official-snapshot-subtitle {
            margin: 0 0 5px 0;
            text-align: center;
            font-size: 12.5px;
            line-height: 1.2;
        }

        .official-snapshot-date {
            margin: 0 0 4px 0;
            text-align: right;
            width: 98%;
            font-size: 8.5px;
            line-height: 1.1;
        }

        .official-snapshot-table {
            width: 99%;
            margin: 0 auto 8px auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.8px;
            line-height: 1.04;
        }

        .official-snapshot-table th,
        .official-snapshot-table td {
            border: 1px solid #111;
            padding: 1.2px 1.2px;
            text-align: center;
            vertical-align: middle;
            background: #fff;
        }

        .official-snapshot-table th {
            font-size: 7.4px;
            line-height: 1.0;
        }

        .official-snapshot-table .snap-rank-col { width: 3.9%; }
        .official-snapshot-table .snap-step-col { width: 4.5%; }
        .official-snapshot-table .snap-license-col { width: 5.1%; }
        .official-snapshot-table .snap-name-col { width: 8.4%; }
        .official-snapshot-table .snap-period-col { width: 2.9%; }
        .official-snapshot-table .snap-arm-col { width: 3.2%; }
        .official-snapshot-table .snap-belong-col { width: 12.5%; }
        .official-snapshot-table .snap-game-col { width: 3.75%; }
        .official-snapshot-table .snap-half-col { width: 4.5%; }
        .official-snapshot-table .snap-total-col { width: 5.1%; }
        .official-snapshot-table .snap-avg-col { width: 4.8%; }
        .official-snapshot-table .snap-wide-total-col { width: 5.5%; }

        .official-snapshot-table .snapshot-belong-td {
            padding-left: 1px;
            padding-right: 1px;
            overflow: hidden;
        }

        .official-snapshot-table .snapshot-belong-cell {
            display: block;
            width: 100%;
            line-height: 1.0;
            overflow: hidden;
            overflow-wrap: normal;
            word-break: keep-all;
            white-space: nowrap;
            letter-spacing: 0;
        }

        .official-snapshot-table .snapshot-belong-size-1 { font-size: 6.0px; }
        .official-snapshot-table .snapshot-belong-size-2 { font-size: 5.4px; }
        .official-snapshot-table .snapshot-belong-size-3 { font-size: 4.9px; }
        .official-snapshot-table .snapshot-belong-size-4 { font-size: 4.5px; }
        .official-snapshot-table .snapshot-belong-size-5 { font-size: 4.1px; }
        .official-snapshot-table .snapshot-belong-size-6 { font-size: 3.7px; }
        .official-snapshot-table .snapshot-belong-size-7 { font-size: 3.4px; }
        .official-snapshot-table .snapshot-belong-size-8 { font-size: 3.1px; }

        .official-snapshot-table .qualified-cell,
        .official-snapshot-table .step-point-cell {
            background: #fff7a8;
        }

        .official-snapshot-table .finalist-ref-cell {
            background: #fff7a8;
            font-size: 7px;
            line-height: 1.35;
            border-bottom: 2.8px solid #111 !important;
        }

        .official-snapshot-table .score-red {
            color: #d00;
        }

        .official-snapshot-table tr.prelim-top-eight-border td {
            border-bottom: 2.8px solid #111 !important;
        }

        .official-snapshot-table tr.semifinal-finalist-border td {
            border-bottom: 2.8px solid #111 !important;
        }

        .official-snapshot-table tr.prelim-qualified-border td {
            border-bottom: 4px double #111 !important;
        }

        .official-snapshot-note {
            width: 96%;
            margin: 0 auto;
            font-size: 8px;
            line-height: 1.25;
        }


        .official-prize-table.non-season-prize-table .rank-col { width: 11%; }
        .official-prize-table.non-season-prize-table .license-col { width: 10%; }
        .official-prize-table.non-season-prize-table .name-col { width: 17%; }
        .official-prize-table.non-season-prize-table .period-col { width: 5%; }
        .official-prize-table.non-season-prize-table .belong-col { width: 42%; }
        .official-prize-table.non-season-prize-table .prize-col { width: 15%; }

    

        .official-single-elimination-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-single-elimination-title {
            text-align: center;
            line-height: 1.08;
            margin: 22px 0 10px 0;
        }

        .official-single-elimination-title-line-1 {
            font-size: 26px;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .official-single-elimination-title-line-2 {
            font-size: 23px;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .official-single-elimination-title-line-3 {
            font-size: 20px;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }

        .official-single-elimination-title-line-4 {
            font-size: 14px;
            letter-spacing: 0.5px;
            margin: 8px auto 0 auto;
            width: 88%;
            text-align: left;
            border-bottom: 1.5px solid #111;
            padding-bottom: 3px;
        }

        .official-single-elimination-meta {
            width: 88%;
            margin: 6px auto 8px auto;
            font-size: 11.5px;
            line-height: 1.35;
            text-align: left;
        }

        .official-single-elimination-wrap {
            width: 100%;
            text-align: center;
            margin: 0;
        }

        .official-single-elimination-image {
            width: 96%;
            max-width: 96%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

</style>
