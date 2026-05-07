@extends('layouts.app')

@section('title', 'Log API')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h2 class="text-title-md2 font-semibold text-gray-800 dark:text-white/90">
        <i class="fas fa-network-wired text-brand-500 mr-2"></i> Log Penggunaan API System
    </h2>
    @if($authFailCount > 0)
    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">
        <span class="animate-pulse h-2 w-2 rounded-full bg-red-500"></span>
        {{ $authFailCount }} Gagal Auth Hari Ini
    </span>
    @endif
</div>

{{-- Filter & Tabs --}}
<div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark mb-4">
    <div class="flex flex-wrap items-center gap-1 px-6 pt-4 border-b border-stroke dark:border-strokedark">
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'all', 'page' => 1]) }}"
           class="px-4 py-2 text-sm font-medium rounded-t border-b-2 transition
                  {{ $tab === 'all' ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">
            Semua
        </a>
        @if($isSuperAdmin)
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'auth_failed', 'page' => 1]) }}"
           class="px-4 py-2 text-sm font-medium rounded-t border-b-2 transition flex items-center gap-2
                  {{ $tab === 'auth_failed' ? 'border-red-500 text-red-500' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">
            <i class="fas fa-ban text-xs"></i> Auth Gagal
            @if($authFailCount > 0)
            <span class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">{{ $authFailCount }}</span>
            @endif
        </a>
        @endif
        <a href="{{ request()->fullUrlWithQuery(['tab' => 'failed', 'page' => 1]) }}"
           class="px-4 py-2 text-sm font-medium rounded-t border-b-2 transition
                  {{ $tab === 'failed' ? 'border-orange-500 text-orange-500' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">
            Gagal Proses
        </a>

        {{-- Filter form --}}
        <form method="GET" class="ml-auto flex items-center gap-2 pb-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="ip" value="{{ request('ip') }}"
                   placeholder="Filter IP..."
                   class="rounded border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-meta-4 dark:text-white focus:border-brand-500 focus:outline-none">
            <input type="date" name="date" value="{{ request('date') }}"
                   class="rounded border border-stroke px-3 py-1.5 text-sm dark:border-strokedark dark:bg-meta-4 dark:text-white focus:border-brand-500 focus:outline-none">
            <button type="submit" class="rounded bg-brand-500 px-3 py-1.5 text-sm text-white hover:bg-brand-600 transition">
                <i class="fas fa-search"></i>
            </button>
            <a href="{{ route('api-logs.index') }}" class="rounded border border-stroke px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 transition dark:border-strokedark dark:text-gray-400">
                Reset
            </a>
        </form>
    </div>
</div>

<div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
    <div class="max-w-full overflow-x-auto">
        <table class="w-full table-auto">
            <thead>
                <tr class="bg-gray-2 text-left dark:bg-meta-4">
                    <th class="py-4 px-4 font-medium text-black dark:text-white xl:pl-11" width="14%">Waktu</th>
                    <th class="py-4 px-4 font-medium text-black dark:text-white" width="10%">Action</th>
                    <th class="py-4 px-4 font-medium text-black dark:text-white" width="10%">UID</th>
                    @if($isSuperAdmin)
                    <th class="py-4 px-4 font-medium text-black dark:text-white" width="12%">Sekolah</th>
                    @endif
                    <th class="py-4 px-4 font-medium text-black dark:text-white text-center" width="8%">Status</th>
                    <th class="py-4 px-4 font-medium text-black dark:text-white" width="22%">Pesan</th>
                    <th class="py-4 px-4 font-medium text-black dark:text-white" width="10%">API Key</th>
                    <th class="py-4 px-4 font-medium text-black dark:text-white" width="10%">IP</th>
                    <th class="py-4 px-4 font-medium text-black dark:text-white text-center" width="8%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="{{ $log->action === 'auth_failed' ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                    <td class="border-b border-[#eee] py-4 px-4 pl-9 dark:border-strokedark xl:pl-11 align-top">
                        <p class="text-black dark:text-white text-sm">{{ $log->created_at }}</p>
                    </td>
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark align-top">
                        @if($log->action === 'auth_failed')
                        <span class="inline-flex items-center gap-1 font-semibold text-red-600 dark:text-red-400 text-sm">
                            <i class="fas fa-ban text-xs"></i> {{ $log->action }}
                        </span>
                        @else
                        <p class="text-black dark:text-white font-medium text-sm">{{ $log->action }}</p>
                        @endif
                    </td>
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark align-top">
                        <p class="text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $log->uid ?? '-' }}</p>
                    </td>
                    @if($isSuperAdmin)
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark align-top">
                        @if($log->school)
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $log->school->name }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-red-500 font-semibold">
                                <i class="fas fa-exclamation-triangle"></i> Tidak Dikenal
                            </span>
                        @endif
                    </td>
                    @endif
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark text-center align-top">
                        @if($log->success)
                            <span class="inline-flex rounded-full bg-success/10 px-3 py-1 text-xs font-medium text-success">Sukses</span>
                        @else
                            <span class="inline-flex rounded-full bg-danger/10 px-3 py-1 text-xs font-medium text-danger">Gagal</span>
                        @endif
                    </td>
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark align-top">
                        <p class="text-gray-600 dark:text-gray-400 text-sm whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($log->message, 80) }}</p>
                    </td>
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark align-top">
                        <code class="rounded bg-gray-100 px-1 py-0.5 text-xs text-brand-500 dark:bg-gray-800">
                            {{ $log->api_key ? substr($log->api_key, 0, 8).'...' : '-' }}
                        </code>
                    </td>
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark align-top">
                        <p class="text-gray-500 dark:text-gray-400 text-sm font-mono">{{ $log->ip_address }}</p>
                    </td>
                    <td class="border-b border-[#eee] py-4 px-4 dark:border-strokedark text-center align-top">
                        @if($log->action !== 'auth_failed' && $log->uid)
                        <button onclick="retryRequest(this, '{{ $log->uid }}', '{{ $log->api_key }}', '{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}')"
                                class="inline-flex items-center rounded bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-opacity-90 transition">
                            <i class="fas fa-redo mr-1"></i> Ulang
                        </button>
                        @elseif($log->action === 'auth_failed')
                        <a href="{{ request()->fullUrlWithQuery(['ip' => $log->ip_address]) }}"
                           class="inline-flex items-center rounded bg-red-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-opacity-90 transition"
                           title="Filter by IP ini">
                            <i class="fas fa-filter mr-1"></i> IP
                        </a>
                        @else
                        <span class="text-gray-400 text-xs">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isSuperAdmin ? 9 : 8 }}" class="border-b border-[#eee] py-8 px-4 dark:border-strokedark text-center text-gray-500">
                        <i class="fas fa-inbox text-2xl mb-2 block"></i>
                        Tidak ada log API untuk filter ini.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="px-5 py-4 border-t border-stroke dark:border-strokedark">
            {{ $logs->links('vendor.pagination.tailwind') }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function retryRequest(btn, uid, apiKey, scannedAt) {
    if (!confirm('Yakin ingin mengulang request ini? Waktu absen akan diproses sesuai jam log: ' + scannedAt)) return;

    let originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');

    fetch('{{ url('/api/rfid') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            uid: uid,
            api_key: apiKey,
            scanned_at: scannedAt
        })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message || 'Request berhasil dikirim ulang');
        window.location.reload();
    })
    .catch(err => {
        alert('Terjadi kesalahan saat menghubungi API.');
        btn.innerHTML = originalText;
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    });
}
</script>
@endpush
