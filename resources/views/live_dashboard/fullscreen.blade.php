<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LIVE MONITORING | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #f1f5f9;
            font-family: 'Inter', system-ui, sans-serif;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 20px;
        }

        /* ── Header ─────────────────────────────── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            border-radius: 20px;
            padding: 18px 32px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .live-dot {
            width: 14px;
            height: 14px;
            background: #ef4444;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
            animation: livePulse 1.5s ease-in-out infinite;
        }

        @keyframes livePulse {

            0%,
            100% {
                box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.3);
            }

            50% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0.05);
            }
        }

        .header-title {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -1px;
            color: #0f172a;
        }

        .header-sep {
            width: 1px;
            height: 40px;
            background: #e2e8f0;
        }

        .header-sub {
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .clock {
            font-size: 52px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            letter-spacing: -2px;
            color: #0f172a;
            background: #f8fafc;
            padding: 4px 24px;
            border-radius: 14px;
        }

        .btn-fullscreen {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 12px 16px;
            border-radius: 14px;
            cursor: pointer;
            font-size: 16px;
            color: #64748b;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
            transition: all .2s;
        }

        .btn-fullscreen:hover {
            background: #f8fafc;
            transform: scale(1.05);
        }

        /* ── Main Layout ─────────────────────────── */
        .main {
            flex: 1;
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 20px;
            overflow: hidden;
            min-height: 0;
        }

        /* ── Left Panel ─────────────────────────── */
        .left-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
            overflow: hidden;
            min-height: 0;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 18px 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border-left: 8px solid;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-height: 0;
        }

        .stat-card.blue {
            border-color: #3b82f6;
        }

        .stat-card.red {
            border-color: #ef4444;
        }

        .stat-card.org {
            border-color: #f97316;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            color: #94a3b8;
        }

        .stat-card.red .stat-label {
            color: #f87171;
        }

        .stat-card.org .stat-label {
            color: #fb923c;
        }

        .stat-value {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }

        .stat-number {
            font-size: 52px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -3px;
            color: #0f172a;
        }

        .stat-card.red .stat-number {
            color: #ef4444;
        }

        .stat-card.org .stat-number {
            color: #f97316;
        }

        .stat-unit {
            font-size: 14px;
            font-weight: 700;
            color: #cbd5e1;
            text-transform: uppercase;
        }

        /* ── 4 mini cards ─────────────────────────── */
        .mini-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            flex-shrink: 0;
        }

        .mini-card {
            border-radius: 16px;
            padding: 14px 18px;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        }

        .mini-card.green {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .mini-card.red {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .mini-card.blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .mini-card.yellow {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .mini-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .mini-number {
            font-size: 32px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -1px;
        }

        /* ── Right Panel (Log) ───────────────────── */
        .right-panel {
            background: white;
            border-radius: 28px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        .log-header {
            padding: 28px 40px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .log-title {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .log-sub {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
            margin-top: 2px;
        }

        .sync-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #eff6ff;
            padding: 8px 16px;
            border-radius: 50px;
        }

        .sync-dot {
            width: 8px;
            height: 8px;
            background: #3b82f6;
            border-radius: 50%;
            animation: livePulse 1.2s infinite;
        }

        .sync-text {
            font-size: 11px;
            font-weight: 800;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .log-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 32px;
        }

        .log-body::-webkit-scrollbar {
            width: 4px;
        }

        .log-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .log-body::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 4px;
        }

        .log-row {
            display: grid;
            grid-template-columns: 100px 120px 1fr 60px;
            align-items: center;
            gap: 16px;
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 10px;
            background: #f8fafc;
            transition: background .15s;
        }

        .log-row:hover {
            background: #f1f5f9;
        }

        .log-time {
            font-size: 20px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: #94a3b8;
        }

        .log-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .badge-masuk {
            background: #dcfce7;
            color: #16a34a;
        }

        .badge-pulang {
            background: #dbeafe;
            color: #2563eb;
        }

        .badge-gerbang {
            background: #ede9fe;
            color: #7c3aed;
        }

        .badge-unknown {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-default {
            background: #f1f5f9;
            color: #64748b;
        }

        .log-message {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .log-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.3px;
        }

        .log-uid {
            font-size: 11px;
            color: #cbd5e1;
            font-family: monospace;
        }

        .log-icon {
            display: flex;
            justify-content: flex-end;
        }

        .icon-ok {
            color: #22c55e;
            font-size: 22px;
        }

        .icon-fail {
            color: #ef4444;
            font-size: 22px;
        }

        .log-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 16px;
            color: #cbd5e1;
        }

        .log-empty i {
            font-size: 56px;
        }

        .log-empty p {
            font-size: 18px;
            font-weight: 600;
        }

        /* ── Footer ─────────────────────────────── */
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 8px;
            flex-shrink: 0;
        }

        .footer-left {
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .footer-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-right span {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
        }

        .footer-right p {
            font-size: 11px;
            font-weight: 700;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
    </style>
</head>

<body>

    {{-- ── HEADER ─────────────────────────────── --}}
    <div class="header">
        <div class="header-left">
            <div class="live-dot"></div>
            <span class="header-title">LIVE MONITORING</span>
            <div class="header-sep"></div>
            <div>
                <p id="live-date" class="header-sub">--</p>
                <p style="font-size:11px;font-weight:700;color:#3b82f6;letter-spacing:1px;">SISTEM ABSENSI {{ auth()->user()->school->name }}
                </p>
            </div>
        </div>
        <div class="clock" id="live-clock">--:--:--</div>
        <button class="btn-fullscreen" onclick="toggleFullscreen()">
            <i class="fas fa-expand"></i>
        </button>
    </div>

    {{-- ── MAIN ────────────────────────────────── --}}
    <div class="main">

        {{-- Left Panel --}}
        <div class="left-panel">
            <div class="stat-card blue">
                <div class="stat-label">Total Siswa</div>
                <div class="stat-value">
                    <span class="stat-number" id="stat-total">--</span>
                    <span class="stat-unit">Siswa</span>
                </div>
            </div>
            <div class="stat-card red">
                <div class="stat-label">Sudah Absen</div>
                <div class="stat-value">
                    <span class="stat-number" id="stat-absen">--</span>
                    <span class="stat-unit">Siswa</span>
                </div>
            </div>
            <div class="stat-card org">
                <div class="stat-label">Belum Absen</div>
                <div class="stat-value">
                    <span class="stat-number" id="stat-belum">--</span>
                    <span class="stat-unit">Orang</span>
                </div>
            </div>
            <div class="mini-grid">
                <div class="mini-card green">
                    <div class="mini-label">Hadir</div>
                    <div class="mini-number" id="stat-hadir">--</div>
                </div>
                <div class="mini-card red">
                    <div class="mini-label">Alpha</div>
                    <div class="mini-number" id="stat-alpha">--</div>
                </div>
                <div class="mini-card blue">
                    <div class="mini-label">Izin</div>
                    <div class="mini-number" id="stat-izin">--</div>
                </div>
                <div class="mini-card yellow">
                    <div class="mini-label">Sakit</div>
                    <div class="mini-number" id="stat-sakit">--</div>
                </div>
            </div>
        </div>

        {{-- Right Panel --}}
        <div class="right-panel">
            <div class="log-header">
                <div>
                    <div class="log-title">Aktivitas Absensi</div>
                </div>
                <div class="sync-badge">
                    <div class="sync-dot"></div>
                    <span class="sync-text">Live Sync</span>
                </div>
            </div>
            <div class="log-body" id="log-body">
                <div class="log-empty">
                    <i class="fas fa-satellite-dish"></i>
                    <p>Menghubungkan ke server...</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── FOOTER ──────────────────────────────── --}}
    <div class="footer">
        <div class="footer-left">&copy; {{ date('Y') }} {{ auth()->user()->school->name }}</div>
        <div class="footer-right">
            <span></span>
            <p id="last-sync">CONNECTED</p>
        </div>
    </div>

    <script>
        // Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('live-date').textContent =
                now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).toUpperCase();
            document.getElementById('live-clock').textContent =
                now.toLocaleTimeString('id-ID', { hour12: false });
        }

        // Fetch data
        async function fetchData() {
            try {
                const res = await fetch('{{ route('live.data') }}');
                const data = await res.json();
                const s = data.stats;

                setText('stat-total', s.total);
                setText('stat-absen', s.absen);
                setText('stat-belum', s.belum);
                setText('stat-hadir', s.hadir);
                setText('stat-alpha', s.alpha);
                setText('stat-izin', s.izin);
                setText('stat-sakit', s.sakit);

                document.getElementById('last-sync').textContent =
                    'SYNC: ' + new Date().toLocaleTimeString('id-ID', { hour12: false });

                renderLogs(data.logs);
            } catch (e) {
                console.error(e);
            }
        }

        function setText(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        function renderLogs(logs) {
            const body = document.getElementById('log-body');
            if (!logs || logs.length === 0) {
                body.innerHTML = `<div class="log-empty">
                    <i class="fas fa-clock"></i>
                    <p>Belum ada aktivitas absensi hari ini</p>
                </div>`;
                return;
            }

            const badgeMap = {
                checkin_success: { cls: 'badge-masuk', label: 'MASUK' },
                checkout_success: { cls: 'badge-pulang', label: 'PULANG' },
            };

            body.innerHTML = logs.map(log => {
                const b = badgeMap[log.action] || { cls: 'badge-default', label: log.action };
                const icon = log.success
                    ? '<i class="fas fa-check-circle icon-ok"></i>'
                    : '<i class="fas fa-times-circle icon-fail"></i>';

                return `<div class="log-row">
                    <div class="log-time">${log.time}</div>
                    <div><span class="log-badge ${b.cls}">${b.label}</span></div>
                    <div class="log-message">
                        <div class="log-name">${log.message}</div>
                        <div class="log-uid">${log.uid || '-'}</div>
                    </div>
                    <div class="log-icon">${icon}</div>
                </div>`;
            }).join('');
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }

        setInterval(updateClock, 1000);
        setInterval(fetchData, 3000);
        updateClock();
        fetchData();
    </script>
</body>

</html>