<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Queue — {{ $doctor->name }}</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; min-height: 100vh; }
        .header { background: #0f172a; color: #fff; padding: 20px 16px; text-align: center; }
        .header h1 { font-size: 18px; font-weight: 800; }
        .header p { font-size: 13px; color: #94a3b8; margin-top: 4px; }
        .live-dot { display: inline-block; width: 8px; height: 8px; background: #4ade80; border-radius: 50%; animation: pulse 2s infinite; margin-right: 6px; vertical-align: middle; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

        .current { margin: 16px; padding: 20px; background: linear-gradient(135deg, #059669, #10b981); border-radius: 16px; color: #fff; text-align: center; }
        .current .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
        .current .token { font-size: 32px; font-weight: 900; margin: 8px 0; }
        .current .name { font-size: 16px; font-weight: 600; }

        .queue-list { margin: 0 16px 16px; }
        .queue-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #fff; border-radius: 12px; margin-bottom: 8px; border: 1px solid #e2e8f0; }
        .queue-item.active { border-color: #10b981; background: #f0fdf4; }
        .position { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; flex-shrink: 0; }
        .position.waiting { background: #f1f5f9; color: #64748b; }
        .position.active { background: #10b981; color: #fff; }
        .queue-info { flex: 1; }
        .queue-info .token { font-size: 14px; font-weight: 700; color: #0f172a; }
        .queue-info .name { font-size: 12px; color: #64748b; margin-top: 2px; }
        .queue-status { font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .queue-status.consulting { background: #dcfce7; color: #166534; }
        .queue-status.waiting { background: #f1f5f9; color: #64748b; }

        .empty { text-align: center; padding: 40px 16px; color: #94a3b8; }
        .refresh-note { text-align: center; padding: 12px; font-size: 11px; color: #94a3b8; }
        .refresh-note a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $doctor->name }}</h1>
        <p>{{ $doctor->department }} · <span class="live-dot"></span>Live Queue</p>
    </div>

    @php
        $current = $appointments->firstWhere('active', true);
        $waiting = $appointments->where('active', false)->values();
    @endphp

    @if($current)
    <div class="current">
        <div class="label">Now Serving</div>
        <div class="token">{{ $current['token'] }}</div>
        <div class="name">{{ $current['name'] }}</div>
    </div>
    @endif

    <div class="queue-list">
        @forelse($waiting as $apt)
        <div class="queue-item">
            <div class="position waiting">{{ $apt['position'] }}</div>
            <div class="queue-info">
                <div class="token">{{ $apt['token'] }}</div>
                <div class="name">{{ $apt['name'] }}</div>
            </div>
            <span class="queue-status waiting">Waiting</span>
        </div>
        @empty
            @if(!$current)
            <div class="empty">
                <p style="font-size:32px;margin-bottom:8px;">🕐</p>
                <p>No patients in queue right now</p>
            </div>
            @endif
        @endforelse
    </div>

    <div class="refresh-note">
        Auto-refreshes every 15 seconds · <a href="javascript:location.reload()">Refresh now</a>
    </div>

    <script>
        // Auto-refresh every 15 seconds
        setTimeout(() => location.reload(), 15000);
    </script>
</body>
</html>
