@extends('layouts.app')

@section('title', 'Pengaturan Sekolah')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-gray-800 dark:text-white/90">
            Pengaturan Sekolah
        </h2>
    </div>

    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div x-data="{ activeTab: 'general' }"
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
            <!-- Tabs Header -->
            <div class="border-b border-gray-200 px-5 dark:border-gray-800">
                <nav class="flex gap-4">
                    <button @click.prevent="activeTab = 'general'"
                        :class="activeTab === 'general' ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-white'"
                        class="border-b-2 py-4 px-2 text-sm font-medium transition-colors">
                        Umum
                    </button>
                    <button @click.prevent="activeTab = 'automation'"
                        :class="activeTab === 'automation' ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-white'"
                        class="border-b-2 py-4 px-2 text-sm font-medium transition-colors">
                        Otomatisasi & Notifikasi
                    </button>
                    @if(config('app.mode') === 'self_hosted' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                    <button @click.prevent="activeTab = 'license'"
                        :class="activeTab === 'license' ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-white'"
                        class="border-b-2 py-4 px-2 text-sm font-medium transition-colors">
                        Lisensi Aplikasi
                    </button>
                    @endif
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Tab Umum -->
                <div x-show="activeTab === 'general'" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-6">

                    <h3
                        class="text-lg font-semibold text-gray-800 dark:text-white/90 border-b border-gray-200 dark:border-gray-800 pb-2">
                        Konfigurasi Umum</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-6">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama
                                    Sekolah</label>
                                <input type="text" name="nama_sekolah"
                                    value="{{ $settings['nama_sekolah'] ?? 'SMK Assuniyah Tumijajar' }}"
                                    class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat
                                    Sekolah (Kop Surat)</label>
                                <textarea name="alamat_sekolah" rows="3"
                                    class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">{{ $settings['alamat_sekolah'] ?? '' }}</textarea>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Kota / Lokasi
                                    Tanda Tangan (Laporan)</label>
                                <input type="text" name="alamat_ttd" value="{{ $settings['alamat_ttd'] ?? 'Jakarta' }}"
                                    placeholder="Contoh: Jakarta"
                                    class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Kepala Sekolah</label>
                                    <input type="text" name="nama_kepala_sekolah" value="{{ $settings['nama_kepala_sekolah'] ?? '' }}"
                                        placeholder="Contoh: Drs. Ahmad Fauzi, M.Pd"
                                        class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">NIP Kepala Sekolah</label>
                                    <input type="text" name="nip_kepala_sekolah" value="{{ $settings['nip_kepala_sekolah'] ?? '' }}"
                                        placeholder="Masukkan NIP"
                                        class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Waka Kesiswaan</label>
                                    <input type="text" name="nama_waka_kesiswaan" value="{{ $settings['nama_waka_kesiswaan'] ?? '' }}"
                                        placeholder="Contoh: Siti Nurhaliza, S.Pd"
                                        class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">NIP Waka Kesiswaan</label>
                                    <input type="text" name="nip_waka_kesiswaan" value="{{ $settings['nip_waka_kesiswaan'] ?? '' }}"
                                        placeholder="Masukkan NIP"
                                        class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                                </div>
                            </div>
                        </div>{{-- end kolom kiri --}}

                        <div class="space-y-6">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Logo
                                    Sekolah</label>
                                <div class="mb-3">
                                    @php
                                        $logo = !empty($settings['logo_filename']) ? $settings['logo_filename'] : 'logo.png';
                                        $isStorage = \Illuminate\Support\Str::startsWith($logo, 'schools/');
                                        $logoUrl = asset('img/logo.png'); // Default

                                        if ($isStorage && file_exists(storage_path('app/public/' . $logo))) {
                                            $logoUrl = asset('storage/' . $logo);
                                        } elseif (!$isStorage && file_exists(public_path('img/' . $logo))) {
                                            $logoUrl = asset('img/' . $logo);
                                        }
                                    @endphp
                                    <div
                                        class="rounded-lg border border-gray-200 p-2 dark:border-gray-800 inline-block bg-white dark:bg-gray-800">
                                        <img src="{{ $logoUrl }}" alt="Logo" id="logo-preview" class="h-24 object-contain">
                                    </div>
                                </div>
                                <input type="file" name="logo" id="logo-input" accept="image/*"
                                    class="w-full cursor-pointer rounded-lg border border-gray-200 bg-transparent text-sm outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-gray-800 dark:file:text-white dark:hover:file:bg-gray-700">
                                <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG, GIF, SVG. Maksimal 10MB</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Kop Surat
                                    Sekolah (Header Laporan)</label>
                                <div class="mb-3">
                                    @php
                                        $kopSurat = !empty($settings['kop_surat']) ? $settings['kop_surat'] : 'default_kop.png';
                                        $isStorageKop = \Illuminate\Support\Str::startsWith($kopSurat, 'schools/');
                                        $kopUrl = asset('img/default_kop.png'); // Default

                                        if ($isStorageKop && file_exists(storage_path('app/public/' . $kopSurat))) {
                                            $kopUrl = asset('storage/' . $kopSurat);
                                        } elseif (!$isStorageKop && file_exists(public_path('img/' . $kopSurat))) {
                                            $kopUrl = asset('img/' . $kopSurat);
                                        }
                                    @endphp
                                    <div
                                        class="rounded-lg border border-gray-200 p-2 dark:border-gray-800 bg-white dark:bg-gray-800 w-full overflow-hidden flex justify-center">
                                        <img src="{{ $kopUrl }}" alt="Kop Surat" id="kop-preview" class="h-24 object-contain max-w-full">
                                    </div>
                                </div>
                                <input type="file" name="kop_surat" id="kop-input" accept="image/jpeg,image/png,image/jpg"
                                    class="w-full cursor-pointer rounded-lg border border-gray-200 bg-transparent text-sm outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-gray-800 dark:file:text-white dark:hover:file:bg-gray-700">
                                <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG. Rekomendasi resolusi lebar (contoh: 1200x200px). Maksimal 5MB.</p>
                            </div>

                            <div
                                class="rounded-lg border border-gray-200 p-4 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <h4 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Pengaturan Absen
                                    Pulang</h4>

                                <div class="mb-4">
                                    <label class="flex items-center cursor-pointer select-none">
                                        <div class="relative">
                                            <input type="checkbox" id="enable_checkout_attendance"
                                                name="enable_checkout_attendance" value="true" class="sr-only" {{ ($settings['enable_checkout_attendance'] ?? 'true') === 'true' ? 'checked' : '' }}>
                                            <div
                                                class="block h-6 w-10 rounded-full bg-gray-300 dark:bg-gray-600 toggle-bg transition">
                                            </div>
                                            <div
                                                class="dot absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition toggle-dot">
                                            </div>
                                        </div>
                                        <div class="ml-3 font-medium text-gray-800 dark:text-white/90 text-sm">Aktifkan
                                            Absen Pulang (Siswa)</div>
                                    </label>
                                    <p class="mt-1 ml-13 text-xs text-gray-500">Jika dinonaktifkan, siswa hanya perlu absen
                                        masuk (1x scan). Jika diaktifkan, siswa perlu absen masuk dan pulang (2x scan).</p>
                                </div>

                                <div>
                                    <label class="flex items-center cursor-pointer select-none">
                                        <div class="relative">
                                            <input type="checkbox" id="enable_checkout_teacher"
                                                name="enable_checkout_teacher" value="true" class="sr-only" {{ ($settings['enable_checkout_teacher'] ?? 'false') === 'true' ? 'checked' : '' }}>
                                            <div
                                                class="block h-6 w-10 rounded-full bg-gray-300 dark:bg-gray-600 toggle-bg transition">
                                            </div>
                                            <div
                                                class="dot absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition toggle-dot">
                                            </div>
                                        </div>
                                        <div class="ml-3 font-medium text-gray-800 dark:text-white/90 text-sm">Aktifkan
                                            Absen Pulang (Karyawan/Guru)</div>
                                    </label>
                                    <p class="mt-1 ml-13 text-xs text-gray-500">Jika diaktifkan, Karyawan/Guru diwajibkan
                                        untuk menempelkan kartu kembali saat Jam Pulang.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Otomatisasi -->
                <div x-show="activeTab === 'automation'" style="display: none;"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" class="space-y-6">

                    <h3
                        class="text-lg font-semibold text-gray-800 dark:text-white/90 border-b border-gray-200 dark:border-gray-800 pb-2">
                        Konfigurasi Jadwal Otomatis (Scheduler)</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Proses Absensi
                                Harian (Auto Bolos/Alpha)</label>
                            <input type="time" name="schedule_process_daily"
                                value="{{ $settings['schedule_process_daily'] ?? '13:30' }}"
                                class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">Waktu sistem memproses siswa yang tidak hadir (Alpha) atau
                                tidak absen pulang (Bolos)</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Laporan Harian</label>
                            <input type="time" name="schedule_daily_report"
                                value="{{ $settings['schedule_daily_report'] ?? '08:15' }}"
                                class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">Waktu pengiriman rekap kehadiran ke grup kelas & wali
                                kelas</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Masa Berlaku Izin Sakit (Hari)</label>
                            <input type="number" name="sakit_max_days" min="1" max="30"
                                value="{{ $settings['sakit_max_days'] ?? '2' }}"
                                class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500">Jumlah hari izin "Sakit" berlaku (Contoh: 2 hari berarti jika hari ini izin sakit, besok otomatis masih izin sakit bila tidak absen masuk).</p>
                        </div>
                    </div>

                </div>

                <!-- Tab Lisensi Aplikasi -->
                @if(config('app.mode') === 'self_hosted' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                <div x-show="activeTab === 'license'" style="display: none;"
                    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" class="space-y-6">

                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 border-b border-gray-200 dark:border-gray-800 pb-2">
                        <i class="fas fa-key text-brand-500 mr-2"></i> Konfigurasi Lisensi
                    </h3>

                    <div class="mb-6 rounded-lg border-l-4 border-warning bg-warning/10 p-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-warning mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium text-warning">Peringatan</h4>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Aplikasi berjalan dalam mode <span class="font-semibold">Self Hosted</span>. Harap masukkan <strong>License Key</strong> yang valid agar fitur-fitur utama dapat berfungsi. Lisensi divalidasi ke server pusat.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-6">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">License Key</label>
                                <input type="text" name="license_key"
                                    value="{{ config('app.license_key') }}"
                                    placeholder="XXXX-XXXX-XXXX-XXXX"
                                    class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white font-mono tracking-wider">
                                <p class="mt-1 text-xs text-gray-500">Masukkan kode lisensi yang diberikan oleh Provider Anda.</p>
                            </div>
                            
                            @php
                                $licenseService = app(\App\Services\LicenseService::class);
                                $licenseStatus = $licenseService->validate();
                            @endphp
                            
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <h4 class="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Status Lisensi Saat Ini</h4>
                                
                                @if($licenseStatus && $licenseStatus['valid'])
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="inline-flex rounded-full bg-success/10 px-3 py-1 text-xs font-medium text-success">
                                            <i class="fas fa-check-circle mr-1"></i> Aktif
                                        </span>
                                    </div>
                                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                        <li><strong>Klien:</strong> {{ $licenseStatus['client_name'] ?? '-' }}</li>
                                        <li><strong>Berlaku s/d:</strong> {{ $licenseStatus['expired_at'] ?? 'Selamanya' }}</li>
                                        <li><strong>Max Sekolah:</strong> {{ ($licenseStatus['max_schools'] ?? 0) === 0 ? 'Unlimited' : $licenseStatus['max_schools'] }}</li>
                                        <li><strong>Max Siswa:</strong> {{ ($licenseStatus['max_students'] ?? 0) === 0 ? 'Unlimited' : $licenseStatus['max_students'] }}</li>
                                        <li><strong>Max Guru:</strong> {{ ($licenseStatus['max_teachers'] ?? 0) === 0 ? 'Unlimited' : $licenseStatus['max_teachers'] }}</li>
                                        <li><strong>Max Bot User:</strong> {{ ($licenseStatus['max_bot_users'] ?? 0) === 0 ? 'Unlimited' : $licenseStatus['max_bot_users'] }}</li>
                                    </ul>
                                @else
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="inline-flex rounded-full bg-danger/10 px-3 py-1 text-xs font-medium text-danger">
                                            <i class="fas fa-times-circle mr-1"></i> Tidak Valid / Expired
                                        </span>
                                    </div>
                                    <p class="text-sm text-danger mt-1">{{ $licenseStatus['message'] ?? 'Lisensi gagal diverifikasi. Pastikan License Key benar.' }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-800">
                    <button type="submit"
                        class="flex w-full md:w-auto items-center justify-center gap-2 rounded-lg bg-brand-500 px-6 py-3 font-medium text-white hover:bg-brand-600 transition">
                        <i class="fas fa-save"></i> Simpan Pengaturan
                    </button>
                </div>
            </div>
        </div>
    </form>



    <style>
        /* Custom Toggle Switch Styles */
        input:checked~.toggle-bg {
            background-color: #3C50E0;
            /* brand-500 */
        }

        input:checked~.toggle-dot {
            transform: translateX(100%);
        }
    </style>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script>
        // Logo preview
        document.getElementById('logo-input').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('logo-preview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Kop Surat preview
        document.getElementById('kop-input').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById('kop-preview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
@endpush