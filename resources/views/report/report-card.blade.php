<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Report</title>
    <style>
        @if (($mode ?? 'pdf') === 'pdf')
            @page {
                size: A4 portrait;
                margin: 5mm;
            }
        @endif

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #000;
            background: {{ ($mode ?? 'pdf') === 'web' ? '#f3f4f6' : '#fff' }};
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 12px !important;

            @if (($mode ?? 'pdf') === 'web')
                padding: 18px;
            @endif
        }

        .page {
            border: 1px solid #d1d5db;
            padding: 6mm;
            background: #fff;

            @if (($mode ?? 'pdf') === 'web')
                max-width: 210mm;
                margin: 0 auto;
                box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
            @endif
        }

        @if (($mode ?? 'pdf') === 'web')
            @media print {
                body {
                    padding: 0;
                    background: #fff;
                }

                .page {
                    max-width: none;
                    margin: 0;
                    box-shadow: none;
                }
            }
        @endif

        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 7px;
            margin-bottom: 7px;
        }

        .header-table,
        .meta-table,
        .summary-table,
        .traits-table,
        .signatures,
        .results-table,
        .meta-box,
        .trait-inner {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
        }

        .header-left {
            width: 68%;
            vertical-align: top;
        }

        .header-right {
            width: 32%;
            text-align: right;
            vertical-align: top;
        }

        .logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            display: block;
        }

        .logo-cell {
            width: 68px;
            vertical-align: top;
        }

        .school-name {
            margin: 0;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .school-line {
            margin: 1px 0 0 0;
            font-size: 10px;
            font-weight: 700;
        }

        .report-badge {
            display: inline-block;
            border: 2px solid #000;
            padding: 4px 7px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .report-session {
            margin-top: 4px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .meta-table td {
            vertical-align: bottom;
            width: 33.33%;
            padding: 4px 4px 5px 0;
        }

        .meta-span-2 {
            width: 66.66%;
        }

        .meta-line {
            width: 100%;
            display: flex;
            align-items: flex-end;
            gap: 4px;
            line-height: 1.4;
        }

        .meta-label {
            font-size: 10px;
            font-weight: 400;
            text-transform: uppercase;
            display: block;
            flex: 0 0 auto;
            line-height: 1.4;
            white-space: nowrap;
        }

        .meta-value {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            text-align: left;
            display: block;
            flex: 1 1 auto;
            line-height: 1.4;
            border-bottom: 1px solid #000;
            min-height: 18px;
            padding: 0 1px 2px;
            width: auto;
            min-width: 0;
            white-space: nowrap;
            vertical-align: bottom;
        }

        .meta-value.plain {
            text-transform: none;
        }

        .results-table {
            table-layout: fixed;
            margin-bottom: 7px;
        }

        .results-table th,
        .results-table td {
            border: 1px solid #000;
            font-size: 9px !important;
            padding: 2px 2px;
            vertical-align: middle;
            word-break: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }

        .results-table th {
            background: #f3f4f6;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            text-align: center;
        }

        .results-table td {
            font-size: 10px;
            line-height: 1.3;
        }

        .results-table td,
        .results-table th,
        .meta-value,
        .comment-body {
            max-width: 100%;
        }

        .subject-col {
            width: 25%;
            font-weight: 700;
            text-transform: uppercase;
        }

        .narrow {
            width: 5%;
            text-align: center;
        }

        .medium {
            width: 7%;
            text-align: center;
        }

        .remark-col {
            width: 11%;
            font-style: italic;
            text-transform: uppercase;
        }

        .summary-table {
            margin-bottom: 7px;
        }

        .summary-table td {
            width: 25%;
            border: 1px solid #000;
            padding: 4px 4px;
            vertical-align: top;
        }

        .grade-scale-box {
            margin-bottom: 7px;
            border: 1px solid #000;
        }

        .grade-scale-title {
            background: #f3f4f6;
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            text-align: center;
        }

        .grade-scale-title small {
            font-size: 9px;
            font-weight: 400;
            text-transform: none;
        }

        .grade-scale-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .grade-scale-table th,
        .grade-scale-table td {
            border-right: 1px solid #000;
            border-bottom: 1px solid #d1d5db;
            padding: 4px 5px;
            font-size: 10px;
        }

        .grade-scale-table th:last-child,
        .grade-scale-table td:last-child {
            border-right: 0;
        }

        .grade-scale-table tr:last-child td {
            border-bottom: 0;
        }

        .grade-scale-table th {
            text-transform: uppercase;
            font-weight: 900;
            background: #fff;
            text-align: left;
        }

        .grade-scale-code {
            width: 12%;
            text-align: center;
            font-weight: 900;
        }

        .grade-scale-name {
            width: 24%;
        }

        .grade-scale-range {
            width: 20%;
            text-align: center;
        }

        .authenticity-table {
            width: 100%;
            margin-bottom: 7px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .authenticity-table td {
            vertical-align: top;
        }

        .auth-left {
            width: 62%;
            padding-right: 6px;
        }

        .auth-right {
            width: 38%;
            padding-left: 6px;
        }

        .verify-box {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
        }

        .verify-title {
            margin: 0 0 2px 0;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .verify-qr {
            margin: 0 auto 3px;
            width: 156px;
            height: 156px;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
        }

        .verify-qr svg {
            display: block;
            width: 100%;
            height: 100%;
        }

        .verify-copy {
            margin: 0;
            font-size: 9px;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .verify-link {
            margin-top: 2px;
            font-size: 8px;
            line-height: 1.25;
            word-break: break-all;
        }

        .summary-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .summary-value {
            display: block;
            font-size: 10px;
            font-weight: 900;
        }

        .traits-table {
            margin-bottom: 7px;
        }

        .traits-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 2px;
        }

        .trait-box {
            border: 1px solid #000;
        }

        .trait-title {
            background: #f3f4f6;
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            text-align: center;
        }

        .trait-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .trait-inner th,
        .trait-inner td {
            border-bottom: 1px solid #d1d5db;
            padding: 4px 5px;
            font-size: 10px;
        }

        .trait-inner th {
            text-transform: uppercase;
            font-weight: 900;
            background: #fff;
        }

        .trait-inner td:last-child,
        .trait-inner th:last-child {
            width: 22%;
            text-align: center;
        }

        .comments {
            margin-bottom: 12px;
        }

        .comment-box {
            border: 1px solid #000;
            padding: 5px 6px;
            margin-bottom: 6px;
        }

        .comment-title {
            margin: 0 0 4px 0;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .comment-body {
            margin: 0;
            border-bottom: 1px dotted #000;
            padding-bottom: 4px;
            font-size: 10px;
            font-weight: 700;
            font-style: italic;
            text-transform: uppercase;
            min-height: 18px;
            line-height: 1.35;
        }

        .signatures td {
            width: 50%;
            padding: 0 10px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-line {
            height: 34px;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
        }

        .signature-label {
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .footer {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <table>
                            <tr>
                                @if ($organizationLogo)
                                    <td class="logo-cell">
                                        <img src="{{ $organizationLogo }}"
                                            alt="{{ $organizationName !== '' ? $organizationName : 'School logo' }}"
                                            class="logo">
                                    </td>
                                @endif
                                <td>
                                    <p class="school-name">{{ $organizationName !== '' ? $organizationName : '—' }}</p>
                                    @if ($organizationAddress !== '')
                                        <p class="school-line">{{ $organizationAddress }}</p>
                                    @endif
                                    @if ($organizationEmail !== '' || $organizationPhone !== '')
                                        <p class="school-line">
                                            {{ trim($organizationEmail . ($organizationEmail !== '' && $organizationPhone !== '' ? ' | ' : '') . $organizationPhone) }}
                                        </p>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="header-right">
                        <span class="report-badge">{{ $reportTitle }}</span>
                        <div class="report-session">{{ $termName }} Term • {{ $yearName }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="meta-table">
            <tr>
                <td colspan="2" class="meta-span-2">
                    <div class="meta-line meta-line-wide"><span class="meta-label">Student Name:</span><span
                            class="meta-value">{{ $studentName }}</span></div>
                </td>
                <td>
                    <div class="meta-line"><span class="meta-label">Admission No.:</span><span
                            class="meta-value">{{ $admissionNumber }}</span></div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="meta-line"><span class="meta-label">Class:</span><span
                            class="meta-value">{{ $className }}</span></div>
                </td>
                <td>
                    <div class="meta-line"><span class="meta-label">Class Position:</span><span
                            class="meta-value plain">{{ $classPosition }} / {{ $classSize }}</span></div>
                </td>
                <td>
                    <div class="meta-line"><span class="meta-label">Report Token:</span><span
                            class="meta-value plain">{{ $token }}</span></div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="meta-span-2">
                    <div class="meta-line meta-line-wide"><span class="meta-label">Academic Session:</span><span
                            class="meta-value plain">{{ $yearName }}</span></div>
                </td>
                <td>
                    <div class="meta-line"><span class="meta-label">Academic Term:</span><span
                            class="meta-value plain">{{ $termName }}</span></div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="meta-line"><span class="meta-label">Average Score:</span><span
                            class="meta-value plain">{{ $averageScore }}</span></div>
                </td>
                <td>
                    <div class="meta-line"><span class="meta-label">Total Score:</span><span
                            class="meta-value plain">{{ $overallTotal }}</span></div>
                </td>
                <td>
                    <div class="meta-line"><span class="meta-label">Date Generated:</span><span
                            class="meta-value plain">{{ $generatedAt->format('d/m/Y') }}</span></div>
                </td>
            </tr>
        </table>

        <table class="results-table">
            <thead>
                <tr>
                    <th class="narrow">#</th>
                    <th class="subject-col">Subject</th>
                    @foreach ($componentColumns ?? [] as $column)
                        <th class="{{ data_get($column, 'component_type') === 'exam' ? 'medium' : 'narrow' }}">
                            {{ data_get($column, 'code') ?: data_get($column, 'label') }}
                        </th>
                    @endforeach
                    <th class="medium">Total</th>
                    <th class="narrow">Grade</th>
                    <th class="narrow">Pos.</th>
                    <th class="remark-col">Remark</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assessmentRows as $row)
                    <tr>
                        <td class="narrow" style="text-align:center;">{{ $row['index'] }}</td>
                        <td class="subject-col">{{ $row['subject'] }}</td>
                        @foreach ($row['componentScores'] ?? [] as $componentScore)
                            <td
                                class="{{ data_get($componentScore, 'component_type') === 'exam' ? 'medium' : 'narrow' }}">
                                {{ data_get($componentScore, 'score', '—') }}
                            </td>
                        @endforeach
                        <td class="medium">{{ $row['total'] }}</td>
                        <td class="narrow">{{ $row['grade'] }}</td>
                        <td class="narrow">{{ $row['position'] }}</td>
                        <td class="remark-col">{{ $row['remark'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 6 + count($componentColumns ?? []) }}"
                            style="text-align:center; padding: 6px;">No assessments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="summary-table">
            <tr>
                @foreach ($summaryRows ?? [] as $summaryRow)
                    <td>
                        <span class="summary-label">{{ $summaryRow['label'] }}</span>
                        <span class="summary-value">{{ $summaryRow['value'] }}</span>
                    </td>
                @endforeach
            </tr>
        </table>

        @if (!empty($gradeScaleRows) || !empty($qrSvg))
            <table class="authenticity-table">
                <tr>
                    <td class="auth-left">
                        @if (!empty($gradeScaleRows))
                            <div class="grade-scale-box">
                                <div class="grade-scale-title">
                                    Grade Scale
                                    @if (!empty($templateName) && $templateName !== '—')
                                        <small>({{ $templateName }})</small>
                                    @endif
                                </div>
                                <table class="grade-scale-table">
                                    <thead>
                                        <tr>
                                            <th class="grade-scale-code">Grade</th>
                                            <th class="grade-scale-range">Score Range</th>
                                            <th>Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($gradeScaleRows as $gradeScaleRow)
                                            <tr>
                                                <td class="grade-scale-code">{{ $gradeScaleRow['code'] }}</td>
                                                <td class="grade-scale-range">{{ $gradeScaleRow['range'] ?: '—' }}</td>
                                                <td>{{ $gradeScaleRow['remark'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </td>
                    <td class="auth-right">
                        @if (!empty($qrSvg))
                            <div class="verify-box">
                                <p class="verify-title">Verify Result</p>
                                <div class="verify-qr">{!! $qrSvg !!}</div>
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
        @endif

        <table class="traits-table">
            <tr>
                <td>
                    <div class="trait-box">
                        <div class="trait-title">Effective Traits</div>
                        <table class="trait-inner">
                            <thead>
                                <tr>
                                    <th>Trait</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($traitRows as $traitRow)
                                    <tr>
                                        <td>{{ $traitRow['effective_label'] }}</td>
                                        <td>{{ $traitRow['effective_grade'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="trait-box">
                        <div class="trait-title">Psychomotor Skills</div>
                        <table class="trait-inner">
                            <thead>
                                <tr>
                                    <th>Skill</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($traitRows as $traitRow)
                                    <tr>
                                        <td>{{ $traitRow['psychomotor_label'] }}</td>
                                        <td>{{ $traitRow['psychomotor_grade'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="comments">
            <div class="comment-box">
                <p class="comment-title">Class Teacher's Comment</p>
                <p class="comment-body">{{ $comment }}</p>
            </div>
            <div class="comment-box">
                <p class="comment-title">Principal's Comment</p>
                <p class="comment-body">{{ $comment }}</p>
            </div>
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-label">Class Teacher's Signature</div>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-label">Principal's Signature &amp; Date</div>
                </td>
            </tr>
        </table>

        <div class="footer">
            {{ collect([$reportFooter ?: null, $generatedAt->format('d/m/Y')])->filter()->implode(' • ') }}
        </div>
    </div>
</body>

</html>
