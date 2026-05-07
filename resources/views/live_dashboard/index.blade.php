@extends('layouts.app')

@section('title', 'Live Monitoring Absensi')

@section('content')
    <div class="flex flex-col gap-6">
        {{-- Header with Digital Clock --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                    </span>
                    LIVE Monitoring Absensi
                    <a href="{{ route('live.fullscreen') }}" target="_blank"
                        class="ml-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-gray-100 dark:bg-meta-4 text-xs font-bold text-gray-600 dark:text-gray-400 hover:bg-brand-500 hover:text-white transition-all">
                        <i class="fas fa-expand"></i> Fullscreen
                    </a>
                </h2>
            </div>
            <div
                class="bg-white dark:bg-boxdark rounded-lg shadow-sm border border-stroke dark:border-strokedark px-6 py-3 flex items-center gap-4">
                <div class="text-right">
                    <p id="live-date" class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider">
                    </p>
                    <p id="live-clock" class="text-2xl font-bold text-brand-500 font-mono"></p>
                </div>
                <i class="fas fa-clock text-3xl text-gray-200 dark:text-gray-700"></i>
            </div>
        </div>

        {{-- Top Row: Main Stats (3 Cards) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Total Siswa --}}
            <div
                class="bg-white dark:bg-boxdark rounded-xl shadow-sm border-l-4 border-blue-500 p-6 flex items-center justify-between transition-all hover:shadow-md">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Siswa</p>
                    <h3 id="stat-total" class="text-3xl font-bold text-gray-800 dark:text-white mt-1">--</h3>
                </div>
                <div class="h-12 w-12 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                    <i class="fas fa-users text-blue-500 text-xl"></i>
                </div>
            </div>

            {{-- Sudah Tap --}}
            <div
                class="bg-white dark:bg-boxdark rounded-xl shadow-sm border-l-4 border-green-500 p-6 flex items-center justify-between transition-all hover:shadow-md">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sudah Absen</p>
                    <h3 id="stat-absen" class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">--</h3>
                </div>
                <div class="h-12 w-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                    <i class="fas fa-fingerprint text-green-500 text-xl"></i>
                </div>
            </div>

            {{-- Belum Tap --}}
            <div
                class="bg-white dark:bg-boxdark rounded-xl shadow-sm border-l-4 border-orange-500 p-6 flex items-center justify-between transition-all hover:shadow-md">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Belum Absen</p>
                    <h3 id="stat-belum" class="text-3xl font-bold text-orange-600 dark:text-orange-400 mt-1">--</h3>
                </div>
                <div class="h-12 w-12 rounded-full bg-orange-50 dark:bg-orange-900/20 flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-orange-500 text-xl"></i>
                </div>
            </div>
        </div>

        {{-- Middle Row: Status Details (4 Cards) --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            {{-- Hadir --}}
            <div
                class="bg-white dark:bg-boxdark rounded-lg p-4 border border-stroke dark:border-strokedark flex items-center gap-4">
                <div
                    class="h-10 w-10 rounded bg-green-50 dark:bg-green-900/20 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check text-green-500"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Hadir</p>
                    <p id="stat-hadir" class="text-xl font-bold text-gray-800 dark:text-white">--</p>
                </div>
            </div>
            {{-- Alpha --}}
            <div
                class="bg-white dark:bg-boxdark rounded-lg p-4 border border-stroke dark:border-strokedark flex items-center gap-4">
                <div class="h-10 w-10 rounded bg-red-50 dark:bg-red-900/20 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-times text-red-500"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Alpha</p>
                    <p id="stat-alpha" class="text-xl font-bold text-gray-800 dark:text-white">--</p>
                </div>
            </div>
            {{-- Izin --}}
            <div
                class="bg-white dark:bg-boxdark rounded-lg p-4 border border-stroke dark:border-strokedark flex items-center gap-4">
                <div
                    class="h-10 w-10 rounded bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-envelope text-blue-500"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Izin</p>
                    <p id="stat-izin" class="text-xl font-bold text-gray-800 dark:text-white">--</p>
                </div>
            </div>
            {{-- Sakit --}}
            <div
                class="bg-white dark:bg-boxdark rounded-lg p-4 border border-stroke dark:border-strokedark flex items-center gap-4">
                <div
                    class="h-10 w-10 rounded bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-briefcase-medical text-yellow-500"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Sakit</p>
                    <p id="stat-sakit" class="text-xl font-bold text-gray-800 dark:text-white">--</p>
                </div>
            </div>
        </div>

        {{-- Bottom Section: Real-time Log --}}
        <div
            class="bg-white dark:bg-boxdark rounded-xl shadow-sm border border-stroke dark:border-strokedark overflow-hidden">
            <div
                class="bg-gray-50 dark:bg-meta-4 px-6 py-4 border-b border-stroke dark:border-strokedark flex items-center justify-between">
                <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-list-ul text-brand-500"></i>
                    Aktivitas Terbaru
                </h3>
                <span class="text-xs text-gray-500 dark:text-gray-400 italic">Terakhir diperbarui: <span
                        id="last-update">--</span></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left bg-gray-100 dark:bg-meta-4/50">
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Waktu</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Aksi</th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Keterangan
                            </th>
                            <th class="px-6 py-3 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase text-center">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody id="log-body">
                        {{-- Data injected via JS --}}
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-400 italic">
                                Memuat data aktivitas...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('live-date').textContent = now.toLocaleDateString('id-ID', options);
            document.getElementById('live-clock').textContent = now.toLocaleTimeString('id-ID', { hour12: false });
        }

        async function fetchLiveData() {
            try {
                const response = await fetch('{{ route('live.data') }}');
                const data = await response.json();

                // Update Stats
                document.getElementById('stat-total').textContent = data.stats.total;
                document.getElementById('stat-absen').textContent = data.stats.absen;
                document.getElementById('stat-belum').textContent = data.stats.belum;
                document.getElementById('stat-hadir').textContent = data.stats.hadir;
                document.getElementById('stat-alpha').textContent = data.stats.alpha;
                document.getElementById('stat-izin').textContent = data.stats.izin;
                document.getElementById('stat-sakit').textContent = data.stats.sakit;

                document.getElementById('last-update').textContent = new Date().toLocaleTimeString('id-ID', { hour12: false });

                // Update Logs
                const logBody = document.getElementById('log-body');
                logBody.innerHTML = '';

                if (data.logs.length === 0) {
                    logBody.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 italic">Belum ada aktivitas hari ini.</td></tr>';
                } else {
                    data.logs.forEach(log => {
                        let actionBadge = '';
                        switch (log.action) {
                            case 'checkin_success': actionBadge = '<span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase">MASUK</span>'; break;
                            case 'checkout_success': actionBadge = '<span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase">PULANG</span>'; break;
                            case 'gate_access': actionBadge = '<span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase">GERBANG</span>'; break;
                            default: actionBadge = `<span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[10px] font-bold uppercase">${log.action}</span>`;
                        }

                        const statusIcon = log.success
                            ? '<i class="fas fa-check-circle text-green-500"></i>'
                            : '<i class="fas fa-exclamation-circle text-red-500"></i>';

                        const row = `
                                <tr class="border-t border-stroke dark:border-strokedark hover:bg-gray-50 dark:hover:bg-meta-4/20 transition-colors animate-fade-in">
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600 dark:text-gray-400">${log.time}</td>
                                    <td class="px-6 py-4">${actionBadge}</td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-medium text-gray-800 dark:text-white">${log.message}</p>
                                        <p class="text-[10px] text-gray-400 font-mono">${log.uid || '-'}</p>
                                    </td>
                                    <td class="px-6 py-4 text-center text-xl">${statusIcon}</td>
                                </tr>
                            `;
                        logBody.insertAdjacentHTML('beforeend', row);
                    });
                }
            } catch (error) {
                console.error('Failed to fetch live data:', error);
            }
        }

        // Initialize
        setInterval(updateClock, 1000);
        setInterval(fetchLiveData, 3000); // Update every 3 seconds
        updateClock();
        fetchLiveData();
    </script>

    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.3s ease-out forwards;
        }
    </style>
@endpush