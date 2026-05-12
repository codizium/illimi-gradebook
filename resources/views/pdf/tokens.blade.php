<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gradebook Tokens</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { font-size: 12px; color: #0f172a; }
        .muted { color: #64748b; }
        .title { font-size: 16px; font-weight: 700; margin: 0 0 4px 0; }
        .meta { font-size: 11px; margin: 0 0 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #475569; background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 999px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .badge-active { background: #ecfdf5; color: #047857; }
        .badge-inactive { background: #f1f5f9; color: #475569; }
        .code { font-family: DejaVu Sans Mono, ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; }
    </style>
</head>
<body>
    <div class="title">Gradebook Result Pins</div>
    <div class="meta muted">
        Export scope: {{ strtoupper((string) ($meta['scope'] ?? 'ALL')) }}
        &nbsp;•&nbsp;
        Generated: {{ \Illuminate\Support\Carbon::parse($meta['generated_at'] ?? now())->format('Y-m-d H:i') }}
        &nbsp;•&nbsp;
        Count: {{ is_countable($tokens ?? null) ? count($tokens) : 0 }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 18%;">Code</th>
                <th style="width: 28%;">Student</th>
                <th style="width: 22%;">Class</th>
                <th style="width: 14%;">Assigned</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($tokens ?? []) as $token)
                <tr>
                    <td class="code">{{ (string) ($token->code ?? '') }}</td>
                    <td>
                        {{ (string) ($token->student?->full_name ?? trim(($token->student?->first_name ?? '').' '.($token->student?->last_name ?? ''))) }}
                        <div class="muted" style="font-size: 10px;">
                            {{ (string) ($token->student?->admission_number ?? '') }}
                        </div>
                    </td>
                    <td>
                        {{ (string) ($token->academicClass?->name ?? '') }}
                        @if(!empty($token->academicClass?->section?->name))
                            <span class="muted">({{ (string) $token->academicClass->section->name }})</span>
                        @endif
                    </td>
                    <td class="muted">
                        {{ $token->assigned_at ? $token->assigned_at->format('Y-m-d') : '—' }}
                    </td>
                    <td>
                        @if((bool) ($token->is_active ?? false))
                            <span class="badge badge-active">Active</span>
                        @else
                            <span class="badge badge-inactive">Inactive</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

