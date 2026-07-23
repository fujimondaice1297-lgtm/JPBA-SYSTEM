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

        .official-title-table {
            width: 100%;
            margin: 4px 0 0 0;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .official-title-table td {
            border: none;
            padding: 0;
            text-align: center;
            vertical-align: middle;
        }

        .official-title-line-1 {
            font-size: 26px;
            letter-spacing: 1px;
            line-height: 1.16;
        }

        .official-title-line-1.official-title-long {
            font-size: 17px;
            letter-spacing: 0;
            line-height: 1.22;
        }

        .official-title-line-1.official-title-extra-long {
            font-size: 15px;
            letter-spacing: 0;
            line-height: 1.22;
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

        .pdf-license-cell {
            text-align: right !important;
            white-space: nowrap;
            padding-right: 4px !important;
            letter-spacing: 0;
            font-variant-numeric: tabular-nums;
        }

        .official-standard-overview-page {
            min-height: 95%;
            padding: 24px 34px 0;
            page-break-after: always;
        }

        .official-standard-overview-title {
            width: 96%;
            margin: 16px auto 7px;
            text-align: center;
            font-size: 23px;
            line-height: 1.22;
        }

        .official-standard-overview-title.official-title-long { font-size: 19px; }
        .official-standard-overview-title.official-title-extra-long { font-size: 17px; }

        .official-standard-overview-subtitle {
            margin: 0 0 20px;
            text-align: center;
            font-size: 18px;
        }

        .official-standard-overview-table {
            width: 88%;
            margin: 0 auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
            line-height: 1.45;
        }

        .official-standard-overview-table th,
        .official-standard-overview-table td {
            border: 1px solid #222;
            padding: 7px 9px;
            vertical-align: top;
        }

        .official-standard-overview-table th {
            width: 18%;
            background: #f0f0f0;
            text-align: center;
        }

        .official-standard-overview-note {
            width: 88%;
            margin: 12px auto 0;
            font-size: 9px;
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

        .official-snapshot-title.official-title-long {
            width: 96%;
            margin-left: auto;
            margin-right: auto;
            font-size: 17px;
            letter-spacing: 0;
            line-height: 1.18;
        }

        .official-snapshot-title.official-title-extra-long {
            width: 96%;
            margin-left: auto;
            margin-right: auto;
            font-size: 15px;
            letter-spacing: 0;
            line-height: 1.18;
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

        .official-standard-snapshot-page.official-snapshot-page-prelim tbody tr {
            height: 16px;
            page-break-inside: avoid;
        }

        .official-round-robin-page {
            page-break-before: always;
            page-break-after: auto;
            padding-top: 4px;
        }

        .official-round-robin-ranking-table,
        .official-round-robin-match-table {
            width: 99%;
            margin: 0 auto 8px;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 6.4px;
            line-height: 1.05;
        }

        .official-round-robin-ranking-table th,
        .official-round-robin-ranking-table td,
        .official-round-robin-match-table th,
        .official-round-robin-match-table td {
            border: 1px solid #111;
            padding: 2px 1px;
            text-align: center;
            vertical-align: middle;
        }

        .official-round-robin-ranking-table th,
        .official-round-robin-match-table th {
            background: #f0f0f0;
            font-size: 6px;
        }

        .official-round-robin-ranking-table .rr-license-col { width: 5.2%; }
        .official-round-robin-ranking-table .rr-name-col { width: 7.8%; }
        .official-round-robin-ranking-table .rr-belong-col { width: 10.5%; }

        .official-round-robin-ranking-table .rr-belong-cell {
            overflow: hidden;
            white-space: nowrap;
        }

        .official-round-robin-ranking-table .rr-belong-cell .snapshot-belong-cell {
            display: block;
            width: 100%;
            overflow: hidden;
            white-space: nowrap;
            line-height: 1;
        }

        .official-round-robin-match-table {
            font-size: 7px;
        }

        .official-round-robin-match-table tbody tr {
            height: 38px;
        }

        .official-round-robin-match-table .rr-match-cell {
            padding: 2px 1px;
            line-height: 1.25;
        }

        .official-round-robin-match-table .rr-opponent-name {
            display: block;
            height: 9px;
            overflow: hidden;
            white-space: nowrap;
            font-size: 5.8px;
        }

        .official-round-robin-match-table .rr-match-bonus {
            font-size: 5.8px;
        }

        .official-prize-table.non-season-prize-table .rank-col { width: 11%; }
        .official-prize-table.non-season-prize-table .license-col { width: 10%; }
        .official-prize-table.non-season-prize-table .name-col { width: 17%; }
        .official-prize-table.non-season-prize-table .period-col { width: 5%; }
        .official-prize-table.non-season-prize-table .belong-col { width: 42%; }
        .official-prize-table.non-season-prize-table .prize-col { width: 15%; }

        .official-prize-table.non-season-prize-table {
            width: 96%;
            font-size: 5.9px;
            margin-bottom: 2px;
        }

        .official-prize-table.non-season-prize-table th,
        .official-prize-table.non-season-prize-table td {
            border-width: 1px;
            padding: 0.3px 1px;
            line-height: 1;
        }

        .official-prize-table.non-season-prize-table th {
            font-size: 5.8px;
        }

        .official-standard-result-page .official-prize-title {
            margin-top: 5px;
            margin-bottom: 3px;
            font-size: 18px;
        }

        .official-standard-result-page .official-top-title {
            margin-top: 4px;
            margin-bottom: 4px;
        }

        .official-standard-result-page .official-logo-wrap {
            height: 31px;
            margin-bottom: 1px;
        }

        .official-standard-result-page .official-logo-image {
            width: 38px;
            max-height: 30px;
        }

        .official-standard-result-page .official-title-line-4 {
            margin-top: 4px;
            font-size: 20px;
        }

        .official-standard-result-page .official-title-line-5 {
            margin-top: 2px;
            font-size: 23px;
        }

        .official-standard-result-page .official-info {
            margin-top: 5px;
            margin-bottom: 5px;
            font-size: 8.5px;
            line-height: 1.15;
        }

        .official-standard-result-page .official-info th,
        .official-standard-result-page .official-info td {
            padding-top: 1px;
            padding-bottom: 1px;
        }

        .official-standard-result-page .official-competition-note {
            margin-bottom: 4px;
            font-size: 8.3px;
            line-height: 1.2;
        }

        .official-standard-result-page .official-record-box,
        .official-standard-result-page .official-borderless-note {
            margin-top: 3px;
            font-size: 7px;
            line-height: 1.15;
        }

    

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

        .official-match-summary-page,
        .official-selection-score-page {
            page-break-before: always;
            page-break-after: auto;
        }

        .official-detail-page-title {
            width: 96%;
            margin: 8px auto 3px auto;
            text-align: center;
            font-size: 18px;
            line-height: 1.18;
            letter-spacing: 0;
        }

        .official-detail-page-title.official-title-long {
            font-size: 16px;
        }

        .official-detail-page-title.official-title-extra-long {
            font-size: 14px;
        }

        .official-detail-page-subtitle {
            margin: 0 0 7px 0;
            text-align: center;
            font-size: 13px;
            line-height: 1.15;
            letter-spacing: 0;
        }

        .official-match-summary-table,
        .official-selection-score-table {
            width: 96%;
            margin: 0 auto 7px auto;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 8px;
            line-height: 1.05;
        }

        .official-match-summary-table th,
        .official-match-summary-table td,
        .official-selection-score-table th,
        .official-selection-score-table td {
            border: 1px solid #111;
            padding: 2px 2px;
            text-align: center;
            vertical-align: middle;
        }

        .official-match-summary-table th,
        .official-selection-score-table th {
            font-size: 7.5px;
        }

        .official-match-summary-table .match-col { width: 21%; }
        .official-match-summary-table .license-col { width: 10%; }
        .official-match-summary-table .name-col { width: 23%; }
        .official-match-summary-table .games-col { width: 30%; }
        .official-match-summary-table .total-col { width: 10%; }
        .official-match-summary-table .winner-col { width: 6%; }
        .official-match-summary-table .winner-row td { background: #fff7a8; }
        .official-match-summary-table .match-start td { border-top-width: 2px; }

        .official-selection-score-table .rank-col { width: 8%; }
        .official-selection-score-table .license-col { width: 12%; }
        .official-selection-score-table .name-col { width: 28%; }
        .official-selection-score-table .game-col { width: 10%; }
        .official-selection-score-table .total-col { width: 12%; }
        .official-selection-score-table .avg-col { width: 10%; }

        .standard-overview-page,
        .standard-awards-page,
        .standard-step-page {
            page-break-after: avoid;
            color: #000;
        }

        .standard-overview-title,
        .standard-page-title,
        .standard-step-title {
            margin: 8px 0 10px;
            text-align: center;
            font-size: 21px;
            line-height: 1.25;
        }

        .standard-overview-title.official-title-long,
        .standard-page-title.official-title-long,
        .standard-step-title.official-title-long {
            font-size: 18px;
        }

        .standard-overview-title.official-title-extra-long,
        .standard-page-title.official-title-extra-long,
        .standard-step-title.official-title-extra-long {
            font-size: 16px;
        }

        .standard-overview-layout,
        .standard-awards-layout,
        .standard-step-layout {
            width: 96%;
            margin: 0 auto;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .standard-overview-layout td,
        .standard-awards-layout td,
        .standard-step-layout td {
            border: none;
            vertical-align: top;
        }

        .standard-overview-content {
            width: 78%;
        }

        .standard-overview-poster {
            width: 22%;
            padding: 30px 0 0 12px;
            text-align: center;
        }

        .standard-overview-poster img {
            max-width: 100%;
            max-height: 230px;
        }

        .standard-overview-detail {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.2px;
            line-height: 1.42;
        }

        .standard-overview-detail th,
        .standard-overview-detail td {
            border: none;
            padding: 2px 1px;
            vertical-align: top;
        }

        .standard-overview-detail th {
            width: 15%;
            text-align: left;
            white-space: nowrap;
        }

        .standard-overview-detail td {
            width: 85%;
            text-align: left;
        }

        .standard-overview-indent {
            display: inline-block;
            padding-left: 10px;
        }

        .standard-overview-schedule {
            margin: 3px 0 0 12px;
            font-size: 8.5px;
            line-height: 1.45;
        }

        .standard-awards-subtitle {
            width: 96%;
            margin: 0 auto 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .standard-awards-subtitle span {
            margin-left: 30px;
            font-size: 9px;
        }

        .standard-awards-list-cell {
            width: 61%;
            padding-right: 8px;
        }

        .standard-awards-notes-cell {
            width: 39%;
            padding-left: 8px;
        }

        .standard-awards-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 5.3px;
            line-height: 1;
        }

        .standard-awards-table th,
        .standard-awards-table td {
            border: 1px solid #111;
            padding: 1px;
            text-align: center;
            vertical-align: middle;
            height: 11px;
            overflow: hidden;
            white-space: nowrap;
        }

        .standard-awards-table th {
            font-size: 5.1px;
            height: 19px;
            background: #f4f1dc;
        }

        .standard-awards-table tr.standard-award-stage-end td {
            border-bottom-width: 2px;
        }

        .standard-awards-table .std-award-rank { width: 7%; }
        .standard-awards-table .std-award-license { width: 8%; }
        .standard-awards-table .std-award-name { width: 12%; }
        .standard-awards-table .std-award-period { width: 4%; }
        .standard-awards-table .std-award-belong { width: 30%; }
        .standard-awards-table .std-award-score { width: 10%; }
        .standard-awards-table .std-award-point { width: 9%; }
        .standard-awards-table .std-award-prize { width: 20%; }

        .standard-award-belong-cell span {
            display: block;
            width: 100%;
            overflow: hidden;
            white-space: nowrap;
            font-size: 4.8px;
        }

        .standard-champion-box {
            min-height: 105px;
            border-bottom: 1px solid #777;
            padding: 6px 4px 8px;
            text-align: center;
        }

        .standard-champion-label {
            display: inline-block;
            margin-right: 12px;
            font-size: 15px;
            font-weight: bold;
        }

        .standard-champion-name {
            display: inline-block;
            font-size: 19px;
            font-weight: bold;
        }

        .standard-champion-meta,
        .standard-champion-message {
            margin-top: 6px;
            font-size: 8px;
        }

        .standard-note-section {
            border-bottom: 1px solid #777;
            padding: 7px 3px;
            font-size: 7.2px;
            line-height: 1.45;
        }

        .standard-note-section h3 {
            margin: 0 0 4px;
            font-size: 9px;
        }

        .standard-note-section p {
            margin: 1px 0;
        }

        .standard-note-footer {
            border-bottom: none;
        }

        .standard-step-page h2 {
            margin: 0 0 8px;
            text-align: center;
            font-size: 14px;
        }

        .standard-step-bracket-cell {
            width: 69%;
            padding-right: 9px;
        }

        .standard-step-summary-cell {
            width: 31%;
            padding: 28px 0 0 9px;
            text-align: center;
        }

        .standard-step-final-rank {
            margin: 0 0 4px;
            font-size: 10px;
            font-weight: bold;
        }

        .standard-step-bracket-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 7px;
            table-layout: fixed;
            font-size: 8px;
        }

        .standard-step-bracket-table td {
            vertical-align: middle;
        }

        .standard-step-seed-label {
            width: 17%;
            text-align: right;
            padding-right: 6px;
        }

        .standard-step-player {
            width: 35%;
            border: 1px solid #111 !important;
            padding: 4px;
            text-align: center;
            font-size: 11px;
        }

        .standard-step-score {
            width: 10%;
            border-bottom: 1px solid #111 !important;
            padding: 4px;
            text-align: center;
            font-size: 11px;
        }

        .standard-step-champion,
        .standard-step-advance {
            width: 38%;
            border: 1px solid #111 !important;
            padding: 5px;
            text-align: center;
        }

        .standard-step-champion span,
        .standard-step-champion strong,
        .standard-step-advance strong {
            display: block;
        }

        .standard-step-champion strong {
            margin-top: 4px;
            font-size: 13px;
            font-weight: normal !important;
        }

        .standard-step-winner-title {
            font-size: 13px;
            font-weight: bold;
        }

        .standard-step-winner-name {
            margin-top: 8px;
            border: 1px solid #111;
            padding: 8px 4px;
            font-size: 16px;
            font-weight: bold;
        }

        .standard-step-winner-note {
            margin-top: 8px;
            font-size: 8px;
        }

        .standard-step-score-area {
            width: 94%;
            margin: 8px auto 0;
        }

        .standard-step-score-block {
            margin: 0 0 5px;
            border-top: 1px solid #aaa;
            padding-top: 3px;
        }

        .standard-step-score-block h3 {
            margin: 0 0 1px;
            font-size: 8px;
        }

        .standard-step-score-block img {
            display: block;
            width: 100%;
            height: auto;
        }

        .official-standard-snapshot-page.official-snapshot-page-prelim .official-snapshot-title {
            margin-top: 4px;
            font-size: 12px;
        }

        .official-standard-snapshot-page.official-snapshot-page-prelim .official-snapshot-subtitle {
            margin-bottom: 2px;
            font-size: 7px;
        }

        .official-standard-snapshot-page.official-snapshot-page-prelim .official-snapshot-table {
            width: 100%;
            margin-bottom: 2px;
            font-size: 4.25px;
            line-height: 0.95;
        }

        .official-standard-snapshot-page.official-snapshot-page-prelim .official-snapshot-table th {
            font-size: 4px;
            padding: 0.5px;
        }

        .official-standard-snapshot-page.official-snapshot-page-prelim .official-snapshot-table td {
            padding: 0.25px 0.4px;
        }

        .official-standard-snapshot-page.official-snapshot-page-prelim tbody tr {
            height: 10px;
        }

        .official-standard-snapshot-page.official-snapshot-page-prelim .snapshot-belong-cell {
            font-size: 3.6px;
        }

</style>
