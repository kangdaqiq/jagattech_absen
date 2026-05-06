@extends('layouts.app')

@section('title', 'Pusat Bantuan (Support)')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h2 class="text-title-md2 font-semibold text-gray-800 dark:text-white/90">
        Pusat Bantuan (Support)
    </h2>
    <nav>
        <ol class="flex items-center gap-2">
            <li>
                <a class="font-medium text-gray-500 dark:text-gray-400" href="{{ route('dashboard') }}">Dashboard /</a>
            </li>
            <li class="font-medium text-brand-500">Support</li>
        </ol>
    </nav>
</div>

<div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
    @forelse($superAdmins as $admin)
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
            <div class="flex flex-col items-center justify-center">
                <div class="mb-4 h-20 w-20 overflow-hidden rounded-full border-4 border-white shadow-md dark:border-gray-800 bg-brand-500 flex items-center justify-center">
                    @php
                        $words = explode(' ', trim($admin->full_name));
                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    @endphp
                    <span class="text-2xl font-bold text-white">{{ $initials }}</span>

                </div>
                <h4 class="mb-1 text-xl font-semibold text-gray-800 dark:text-white/90">{{ $admin->full_name }}</h4>
                <p class="mb-4 text-sm font-medium text-brand-500">Super Administrator</p>

                <div class="flex flex-col gap-2 w-full mt-2 border-t border-gray-100 dark:border-gray-800 pt-4">
                    <a href="mailto:{{ $admin->email }}" class="flex w-full items-center justify-center gap-2 rounded-lg bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:bg-gray-800/50 dark:text-gray-300 dark:hover:bg-gray-800 transition">
                        <i class="fas fa-envelope text-gray-500"></i>
                        {{ $admin->email }}
                    </a>
                    <a href="https://wa.me/6281327735093" target="_blank" class="flex w-full items-center justify-center gap-2 rounded-lg bg-success-50 text-success-600 px-4 py-3 text-sm font-medium hover:bg-success-100 dark:bg-success-500/15 dark:text-success-500 dark:hover:bg-success-500/25 transition">
                        <i class="fab fa-whatsapp text-lg"></i>
                        0813 2773 5093
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-1 md:col-span-2 xl:col-span-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
                <i class="fas fa-headset text-4xl text-gray-400 mb-4"></i>
                <h3 class="mb-2 text-xl font-semibold text-gray-800 dark:text-white/90">Belum ada Super Admin terdaftar</h3>
                <p class="text-gray-500 dark:text-gray-400">Silakan hubungi administrator sistem secara langsung.</p>
            </div>
        </div>
    @endforelse
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
    <h4 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Butuh Bantuan Lebih Lanjut?</h4>
    <p class="text-gray-500 dark:text-gray-400 mb-4">
        Jika Anda mengalami kendala teknis atau masalah serius pada Sistem Absensi RFID v2.0, silakan hubungi tim dukungan kami melalui kontak Super Administrator di atas.
    </p>
    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
        <span class="flex items-center gap-2"><i class="fas fa-check-circle text-success-500"></i> Server Status: Online</span>
        <span class="flex items-center gap-2"><i class="fas fa-code-branch text-brand-500"></i> Version: 2.0.0</span>
    </div>
</div>
@endsection
