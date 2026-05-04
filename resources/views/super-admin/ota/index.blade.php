@extends('layouts.app')

@section('title', 'OTA Firmware Updates')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-title-md2 font-semibold text-gray-800 dark:text-white/90">
            <i class="fas fa-microchip text-brand-500 mr-2"></i> OTA Firmware Updates
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola pembaruan firmware perangkat RFIDv2 secara online.</p>
    </div>
</div>

@if(session('success'))
<div class="mb-6 rounded-lg bg-success-50 p-4 border border-success-200 text-success-600 dark:bg-success-500/15 dark:border-success-500/20 dark:text-success-400">
    <div class="flex items-center">
        <i class="fas fa-check-circle mr-3 fa-lg"></i>
        <p class="font-medium">{{ session('success') }}</p>
    </div>
</div>
@endif

<div class="grid grid-cols-1 gap-4 md:gap-6 2xl:gap-7.5 xl:grid-cols-2">
    <!-- Left Column: Upload Form -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark sm:p-7.5">
        <h4 class="mb-4 text-xl font-bold text-gray-800 dark:text-white/90">
            <i class="fas fa-upload text-brand-500 mr-2"></i> Upload Firmware Baru
        </h4>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Pilih file binary (.bin) hasil compile dari Arduino IDE untuk perangkat RFIDV2.</p>
        
        <form action="{{ route('super-admin.ota.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <div>
                <label class="mb-2.5 block text-sm font-medium text-gray-800 dark:text-white/90">Pilih File Firmware (.bin)</label>
                <div class="relative">
                    <input type="file" name="firmware" class="w-full cursor-pointer rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 file:mr-4 file:rounded file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-500 hover:file:bg-brand-100 dark:file:bg-brand-500/15 dark:file:text-brand-400" accept=".bin" required>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Pastikan nama file sesuai atau sistem otomatis mengubahnya menjadi <code class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-brand-500 dark:bg-gray-800">RFIDV2.bin</code></p>
            </div>
            
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 p-3.5 font-medium text-white hover:bg-brand-600 transition focus:ring-4 focus:ring-brand-500/20">
                <i class="fas fa-cloud-upload-alt"></i> Upload & Publikasikan
            </button>
        </form>
    </div>

    <!-- Right Column: Status -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark sm:p-7.5">
        <h4 class="mb-4 text-xl font-bold text-gray-800 dark:text-white/90">
            <i class="fas fa-info-circle text-info-500 mr-2"></i> Status Firmware Saat Ini
        </h4>
        
        @php
            $binPath = public_path('ota/RFIDV2.bin');
            $exists = file_exists($binPath);
        @endphp

        @if($exists)
            <div class="mb-6 flex items-center gap-5 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-800/50">
                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-success-50 text-success-500 dark:bg-success-500/15">
                    <i class="fas fa-file-code fa-2xl"></i>
                </div>
                <div>
                    <h5 class="text-lg font-bold text-gray-800 dark:text-white/90">RFIDV2.bin</h5>
                    <div class="mt-1 flex flex-col gap-1 sm:flex-row sm:gap-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400"><i class="fas fa-weight-hanging mr-1"></i> {{ round(filesize($binPath) / 1024, 2) }} KB</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400"><i class="far fa-clock mr-1"></i> {{ date('d M Y, H:i', filemtime($binPath)) }}</p>
                    </div>
                </div>
            </div>
            
            <div class="rounded-lg bg-info-50 p-4 border border-info-200 text-info-700 dark:bg-info-500/15 dark:border-info-500/20 dark:text-info-300">
                <p class="text-sm font-medium"><i class="fas fa-link mr-1"></i> URL Update:</p>
                <div class="mt-2 rounded bg-white/60 p-2.5 text-xs font-mono dark:bg-gray-900/50 break-all select-all">
                    {{ url('ota/RFIDV2.bin') }}
                </div>
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-8 text-center rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-error-50 text-error-500 dark:bg-error-500/15">
                    <i class="fas fa-times fa-2xl"></i>
                </div>
                <h5 class="text-lg font-semibold text-gray-800 dark:text-white/90">Belum Ada Firmware</h5>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Silakan upload firmware terbaru terlebih dahulu di sebelah kiri.</p>
            </div>
        @endif

        <div class="mt-8 border-t border-gray-100 pt-6 dark:border-gray-800">
            <h6 class="mb-3 text-sm font-bold uppercase text-gray-500 dark:text-gray-400">Panduan Update:</h6>
            <ul class="list-inside list-disc space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <li>Buka menu Config pada perangkat RFIDv2 (Masuk ke Mode AP WiFi perangkat).</li>
                <li>Pilih menu <strong class="text-gray-800 dark:text-white/90">OTA Online Update</strong>.</li>
                <li>Klik tombol <strong class="text-gray-800 dark:text-white/90">🚀 Mulai Update</strong>.</li>
                <li>Perangkat akan mendownload file dari server ini dan melakukan restart otomatis.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
