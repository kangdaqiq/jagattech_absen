@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
<div x-data="bulkSiswaActions()">
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h2 class="text-title-md2 font-semibold text-gray-800 dark:text-white/90">
        Data Siswa
    </h2>
    <div class="flex flex-wrap gap-2">
        <button @click="$dispatch('open-modal', 'modalImportSiswa')" class="inline-flex items-center justify-center gap-2.5 rounded-lg bg-success-500 px-4 py-2 text-center font-medium text-white hover:bg-success-600 transition">
            <i class="fas fa-file-excel"></i> Import Excel
        </button>
        <button @click="$dispatch('open-modal', 'modalTambahSiswa')" class="inline-flex items-center justify-center gap-2.5 rounded-lg bg-brand-500 px-4 py-2 text-center font-medium text-white hover:bg-brand-600 transition">
            <i class="fas fa-plus"></i> Tambah Siswa
        </button>
    </div>
</div>

<div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-dark">
    {{-- Alert Success / Error --}}
    @if(session('success'))
        <div class="m-5 flex items-start gap-3 rounded-xl border border-success-200 bg-success-50 px-4 py-3 dark:border-success-500/20 dark:bg-success-500/10">
            <div class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-success-500 text-white">
                <i class="fas fa-check text-xs"></i>
            </div>
            <p class="text-sm font-medium text-success-800 dark:text-success-400">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="m-5 flex items-start gap-3 rounded-xl border border-error-200 bg-error-50 px-4 py-3 dark:border-error-500/20 dark:bg-error-500/10">
            <div class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-error-500 text-white">
                <i class="fas fa-exclamation text-xs"></i>
            </div>
            <p class="text-sm font-medium text-error-800 dark:text-error-400">{!! session('error') !!}</p>
        </div>
    @endif
    <!-- Header & Search -->
    <div class="flex flex-col sm:flex-row justify-between items-center px-5 py-4 border-b border-gray-200 dark:border-gray-800 gap-4">
        <div class="flex items-center gap-3">
            <h4 class="font-semibold text-gray-800 dark:text-white/90">Tabel Data Siswa</h4>
            
            <!-- Bulk Actions Toolbar -->
            <div x-show="selected.length > 0" x-cloak class="flex items-center gap-2 border-l border-gray-200 pl-3 dark:border-gray-700" style="display: none;">
                <span class="text-sm font-medium text-brand-500" x-text="selected.length + ' terpilih'"></span>
                <button @click="$dispatch('open-modal', 'modalBulkEditKelas')" class="rounded bg-brand-50 px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-100 dark:bg-brand-500/15 dark:text-brand-500 dark:hover:bg-brand-500/25">Edit Kelas</button>
                <button @click="$dispatch('open-modal', 'modalBulkHapusSiswa')" class="rounded bg-error-50 px-2 py-1 text-xs font-medium text-error-600 hover:bg-error-100 dark:bg-error-500/15 dark:text-error-500 dark:hover:bg-error-500/25">Hapus</button>
            </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            {{-- Form Filter & Search --}}
            <form action="{{ route('siswa.index') }}" method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                {{-- Filter Kelas --}}
                <div class="relative">
                    <select name="kelas_id" onchange="this.form.submit()" 
                        class="appearance-none w-full sm:w-48 rounded-lg border border-gray-200 bg-transparent py-2 pl-4 pr-10 text-sm outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:focus:border-brand-500 text-gray-800 dark:text-white/90 cursor-pointer">
                        <option value="">Semua Kelas</option>
                        @foreach ($kelas as $k)
                            <option value="{{ $k->id }}" {{ request('kelas_id') == $k->id ? 'selected' : '' }}>{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </div>
                </div>

                {{-- Server-side Search --}}
                <div class="relative w-full sm:w-64">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Siswa..." 
                        class="w-full rounded-lg border border-gray-200 bg-transparent py-2 pl-4 pr-10 text-sm outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:focus:border-brand-500 text-gray-800 dark:text-white/90">
                    <button type="submit" class="absolute right-0 top-0 h-full px-3 text-gray-500 hover:text-brand-500 dark:text-gray-400">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="max-w-full overflow-x-auto">
        <table class="w-full table-auto">
            <thead>
                <tr class="bg-gray-50 text-left dark:bg-gray-800/50 text-gray-800 dark:text-white/90 font-medium text-sm">
                    <th class="px-4 py-4 w-10 text-center">
                        <input type="checkbox" @change="toggleAll" x-bind:checked="isAllSelected" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800">
                    </th>
                    <th class="px-4 py-4">No</th>
                    <th class="px-4 py-4">Nama</th>
                    <th class="px-4 py-4">NIS</th>
                    <th class="px-4 py-4">Tgl Lahir</th>
                    <th class="px-4 py-4">Kelas</th>
                    <th class="px-4 py-4">No WA Siswa / Ortu</th>
                    <th class="px-4 py-4 text-center">UID RFID</th>
                    <th class="px-4 py-4 text-center">ID Finger</th>
                    <th class="px-4 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                @forelse ($siswa as $s)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 text-center">
                            <input type="checkbox" value="{{ $s->id }}" x-model="selected" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800">
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800">
                            <p class="text-gray-500 dark:text-gray-400">{{ $loop->iteration + $siswa->firstItem() - 1 }}</p>
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800">
                            <p class="font-medium text-gray-800 dark:text-white/90">{{ $s->nama }}</p>
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800">
                            <p class="text-gray-500 dark:text-gray-400">{{ $s->nis }}</p>
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800">
                            <p class="text-gray-500 dark:text-gray-400">{{ $s->tgl_lahir ? \Carbon\Carbon::parse($s->tgl_lahir)->format('d-m-Y') : '-' }}</p>
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800">
                            <p class="text-gray-500 dark:text-gray-400">{{ $s->kelas->nama_kelas ?? '-' }}</p>
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800">
                            <p class="text-gray-500 dark:text-gray-400">S: {{ $s->no_wa ?: '-' }}</p>
                            <p class="text-gray-500 dark:text-gray-400">O: {{ $s->wa_ortu ?: '-' }}</p>
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 text-center">
                            @if($s->uid_rfid)
                                <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-500">{{ $s->uid_rfid }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 text-center">
                            @if($s->id_finger)
                                <span class="inline-flex rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-600 dark:bg-success-500/15 dark:text-success-500">{{ $s->id_finger }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="border-b border-gray-100 px-4 py-4 dark:border-gray-800">
                            <div class="flex items-center justify-center gap-2" x-data="{}">
                                <!-- Enroll RFID -->
                                <button class="btnEnroll text-blue-500 hover:text-blue-700 hover:bg-blue-50 p-2 rounded-lg transition" 
                                    data-id="{{ $s->id }}" data-nama="{{ $s->nama }}" data-uid="{{ $s->uid_rfid }}"
                                    @click="$dispatch('open-modal', 'modalEnrollRFID')" title="Registrasi RFID">
                                    <i class="fas fa-rss"></i>
                                </button>
                                
                                <!-- Enroll Fingerprint -->
                                <button class="btnEnrollFinger text-green-500 hover:text-green-700 hover:bg-green-50 p-2 rounded-lg transition" 
                                    data-id="{{ $s->id }}" data-nama="{{ $s->nama }}" data-finger="{{ $s->id_finger }}"
                                    @click="$dispatch('open-modal', 'modalEnrollFinger')" title="Registrasi Sidik Jari">
                                    <i class="fas fa-fingerprint"></i>
                                </button>
                                
                                <!-- Edit -->
                                <button class="btnEdit text-orange-500 hover:text-orange-700 hover:bg-orange-50 p-2 rounded-lg transition" 
                                    data-id="{{ $s->id }}" data-nama="{{ $s->nama }}" data-nis="{{ $s->nis }}" data-tgl_lahir="{{ $s->tgl_lahir }}"
                                    data-kelas="{{ $s->kelas_id }}" data-wa="{{ $s->no_wa }}" data-wa_ortu="{{ $s->wa_ortu }}" data-uid="{{ $s->uid_rfid }}"
                                    @click="$dispatch('open-modal', 'modalEditSiswa')" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <!-- Delete -->
                                <button class="btnHapus text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition" 
                                    data-id="{{ $s->id }}" data-nama="{{ $s->nama }}"
                                    @click="$dispatch('open-modal', 'modalHapusSiswa')" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="border-b border-gray-100 px-4 py-8 dark:border-gray-800 text-center text-gray-500 dark:text-gray-400">
                            Tidak ada data siswa ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-800">
        {{ $siswa->links('vendor.pagination.tailwind') }}
    </div>
</div>

<!-- ========================= MODALS ========================= -->

<!-- Modal Bulk Edit Kelas -->
<x-ui.modal id="modalBulkEditKelas" :is-open="false">
    <div class="p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Edit Kelas Masal</h3>
            <button @click="open = false" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <p class="mb-4 text-gray-600 dark:text-gray-400">Pilih kelas baru untuk <span x-text="selected.length" class="font-bold text-brand-500"></span> siswa yang dipilih.</p>
        <div class="mb-6">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Kelas</label>
            <select id="bulk_kelas_id" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <option value="">-- Pilih Kelas --</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
            <button type="button" @click="bulkUpdateKelas(document.getElementById('bulk_kelas_id').value)" class="rounded-lg bg-brand-500 px-4 py-2 text-white hover:bg-brand-600">Simpan Perubahan</button>
        </div>
    </div>
</x-ui.modal>

<!-- Modal Bulk Hapus -->
<x-ui.modal id="modalBulkHapusSiswa" :is-open="false">
    <div class="p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-xl font-bold text-error-500">Hapus Masal Siswa</h3>
            <button @click="open = false" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-gray-700 dark:text-gray-300 mb-6">Yakin ingin menghapus secara permanen <span x-text="selected.length" class="font-bold text-error-500"></span> siswa yang dipilih?</p>
        <div class="flex justify-end gap-3">
            <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
            <button type="button" @click="bulkDelete()" class="rounded-lg bg-error-500 px-4 py-2 text-white hover:bg-error-600">Hapus</button>
        </div>
    </div>
</x-ui.modal>

<!-- Modal Tambah -->
<x-ui.modal id="modalTambahSiswa" :is-open="false">
    <div class="p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Tambah Siswa</h3>
            <button @click="open = false" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form action="{{ route('siswa.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Siswa</label>
                    <input type="text" name="nama" required class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">NIS</label>
                    <input type="text" name="nis" required class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Lahir</label>
                    <input type="date" name="tgl_lahir" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kelas</label>
                    <select name="kelas_id" required class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($kelas as $k)
                            <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">No WhatsApp Siswa</label>
                    <input type="text" name="no_wa" placeholder="08xxx atau 628xxx" pattern="^(08|628)[0-9]{8,13}$" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                    <p class="mt-1 text-xs text-gray-500">Format: 08xxx atau 628xxx (8-13 digit)</p>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">No WhatsApp Ortu</label>
                    <input type="text" name="wa_ortu" placeholder="08xxx atau 628xxx" pattern="^(08|628)[0-9]{8,13}$" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                    <p class="mt-1 text-xs text-gray-500">Format: 08xxx atau 628xxx (8-13 digit)</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-white hover:bg-brand-600">Simpan</button>
            </div>
        </form>
    </div>
</x-ui.modal>

<!-- Modal Edit -->
<x-ui.modal id="modalEditSiswa" :is-open="false">
    <div class="p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Edit Siswa</h3>
            <button @click="open = false" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form action="#" method="POST" id="formEditSiswa">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Siswa</label>
                    <input type="text" name="nama" id="edit_nama" required class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">NIS</label>
                    <input type="text" name="nis" id="edit_nis" required class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Lahir</label>
                    <input type="date" name="tgl_lahir" id="edit_tgl_lahir" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kelas</label>
                    <select name="kelas_id" id="edit_kelas_id" required class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                        <option value="">-- Pilih Kelas --</option>
                        @foreach ($kelas as $k)
                            <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">No WhatsApp Siswa</label>
                    <input type="text" name="no_wa" id="edit_no_wa" placeholder="08xxx atau 628xxx" pattern="^(08|628)[0-9]{8,13}$" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">No WhatsApp Ortu</label>
                    <input type="text" name="wa_ortu" id="edit_wa_ortu" placeholder="08xxx atau 628xxx" pattern="^(08|628)[0-9]{8,13}$" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">UID RFID (readonly)</label>
                    <input type="text" name="uid_rfid" id="edit_uid_rfid" readonly class="w-full rounded-lg border border-gray-200 bg-gray-100 px-4 py-2 outline-none dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-white hover:bg-brand-600">Update</button>
            </div>
        </form>
    </div>
</x-ui.modal>

<!-- Modal Hapus -->
<x-ui.modal id="modalHapusSiswa" :is-open="false">
    <div class="p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-xl font-bold text-error-500">Hapus Siswa</h3>
            <button @click="open = false" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form action="#" method="POST" id="formHapusSiswa">
            @csrf
            @method('DELETE')
            <p class="text-gray-700 dark:text-gray-300 mb-6">Yakin ingin menghapus siswa: <strong id="hapus_nama" class="text-gray-900 dark:text-white"></strong>?</p>
            <div class="flex justify-end gap-3">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
                <button type="submit" class="rounded-lg bg-error-500 px-4 py-2 text-white hover:bg-error-600">Hapus</button>
            </div>
        </form>
    </div>
</x-ui.modal>

<!-- Modal Import -->
<x-ui.modal id="modalImportSiswa" :is-open="false">
    <div class="p-6" x-data="importFormSiswa()">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Import Data Siswa</h3>
            <button @click="open = false; if(!isImporting) reset();" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        
        <form @submit.prevent="submitForm" x-show="!isImporting && !isFinished" enctype="multipart/form-data">
            @csrf
            <div class="mb-4 rounded-lg bg-info-50 p-4 text-sm text-info-700 dark:bg-info-500/15 dark:text-info-500">
                <i class="fas fa-info-circle mr-1"></i> Gunakan file Excel (.xlsx) dengan format kolom: <strong>Nama, NIS, Tgl Lahir, Kelas, WA Siswa, WA Ortu</strong>.
            </div>
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih File Excel</label>
                    <input type="file" x-ref="fileInput" required accept=".xlsx,.xls,.csv" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <a href="{{ route('siswa.template') }}" class="text-sm font-medium text-brand-500 hover:underline"><i class="fas fa-download mr-1"></i> Download Template Excel</a>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">Batal</button>
                <button type="submit" class="rounded-lg bg-success-500 px-4 py-2 text-white hover:bg-success-600">Import Data</button>
            </div>
        </form>

        <!-- Progress Area -->
        <div x-show="isImporting" class="py-4" style="display: none;">
            <div class="mb-2 flex justify-between text-sm font-medium">
                <span class="text-gray-700 dark:text-gray-300">Sedang memproses...</span>
                <span class="text-brand-500" x-text="progress + '%'"></span>
            </div>
            <div class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-2.5 rounded-full bg-brand-500 transition-all duration-300 ease-out" :style="'width: ' + progress + '%'"></div>
            </div>
            <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">Mohon tunggu, memproses data bisa memakan waktu.</p>
        </div>

        <!-- Success/Error Message -->
        <div x-show="isFinished" class="py-4 text-center" style="display: none;">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full" :class="isSuccess ? 'bg-success-100 text-success-500' : 'bg-error-100 text-error-500'">
                <i class="fas fa-2x" :class="isSuccess ? 'fa-check' : 'fa-times'"></i>
            </div>
            <h4 class="mb-2 text-lg font-bold text-gray-800 dark:text-white/90" x-text="isSuccess ? 'Selesai' : 'Terjadi Kesalahan'"></h4>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6" x-text="message"></p>
            <button type="button" @click="if(isSuccess) location.reload(); else { isFinished = false; progress = 0; }" class="rounded-lg bg-brand-500 px-6 py-2 text-white hover:bg-brand-600">
                <span x-text="isSuccess ? 'Kembali' : 'Coba Lagi'"></span>
            </button>
        </div>
    </div>
</x-ui.modal>

<!-- Modal Enroll RFID -->
<x-ui.modal id="modalEnrollRFID" :is-open="false">
    <div class="p-6 text-center">
        <div class="flex justify-end mb-2">
            <button @click="open = false" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-500 dark:bg-brand-500/15">
            <i class="fas fa-id-card fa-2x"></i>
        </div>
        <h3 class="mb-2 text-2xl font-bold text-gray-800 dark:text-white/90">Registrasi RFID</h3>
        <h5 id="enroll_nama" class="font-medium text-gray-600 dark:text-gray-400 mb-6"></h5>

        <div id="uid_wrapper" class="hidden mb-4 rounded-lg bg-success-50 p-3 text-success-700 dark:bg-success-500/15 dark:text-success-500">
            UID Terdaftar: <strong id="enroll_uid" class="text-lg"></strong>
        </div>

        <div id="enroll_status" class="mb-6 h-10 flex items-center justify-center"></div>

        <div class="flex flex-col gap-3">
            <button type="button" class="rounded-lg bg-brand-500 p-3 font-medium text-white hover:bg-brand-600 transition" id="btnMulaiEnroll">
                <i class="fas fa-rss mr-1"></i> Mulai Scan Kartu
            </button>
            <button type="button" class="rounded-lg bg-error-500 p-3 font-medium text-white hover:bg-error-600 transition disabled:opacity-50 disabled:cursor-not-allowed" id="btnHapusUID" disabled>
                <i class="fas fa-trash mr-1"></i> Hapus UID
            </button>
        </div>
    </div>
</x-ui.modal>

<!-- Modal Enroll Fingerprint -->
<x-ui.modal id="modalEnrollFinger" :is-open="false">
    <div class="p-6 text-center">
        <div class="flex justify-end mb-2">
            <button @click="open = false" class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-success-50 text-success-500 dark:bg-success-500/15">
            <i class="fas fa-fingerprint fa-2x"></i>
        </div>
        <h3 class="mb-2 text-2xl font-bold text-gray-800 dark:text-white/90">Registrasi Sidik Jari</h3>
        <h5 id="enroll_finger_nama" class="font-medium text-gray-600 dark:text-gray-400 mb-6"></h5>

        <div id="finger_id_wrapper" class="hidden mb-4 rounded-lg bg-success-50 p-3 text-success-700 dark:bg-success-500/15 dark:text-success-500">
            ID Sidik Jari: <strong id="enroll_finger_id" class="text-lg"></strong>
        </div>

        <div id="enroll_finger_status" class="mb-6 h-10 flex items-center justify-center"></div>

        <div class="mb-6 text-left">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Device</label>
            <select id="finger_device_id" class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                <option value="">-- Pilih Device --</option>
                @foreach($devices as $dev)
                    <option value="{{ $dev->id }}">{{ $dev->name }} ({{ ucfirst($dev->type) }})</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-3">
            <button type="button" class="rounded-lg bg-success-500 p-3 font-medium text-white hover:bg-success-600 transition" id="btnMulaiEnrollFinger">
                <i class="fas fa-fingerprint mr-1"></i> Mulai Scan Sidik Jari
            </button>
            <button type="button" class="rounded-lg bg-error-500 p-3 font-medium text-white hover:bg-error-600 transition disabled:opacity-50 disabled:cursor-not-allowed" id="btnHapusFinger" disabled>
                <i class="fas fa-trash mr-1"></i> Hapus Sidik Jari
            </button>
        </div>
    </div>
</x-ui.modal>
</div>
@endsection

@push('scripts')
    <!-- Minimal jQuery for legacy AJAX functionality, consider refactoring to Alpine/Fetch later -->
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Edit
            $('.btnEdit').on('click', function () {
                var id = $(this).data('id');
                var nama = $(this).data('nama');
                var nis = $(this).data('nis');
                var tgl_lahir = $(this).data('tgl_lahir');
                var kelas = $(this).data('kelas');
                var wa = $(this).data('wa');
                var wa_ortu = $(this).data('wa_ortu');
                var uid = $(this).data('uid');

                $('#edit_nama').val(nama);
                $('#edit_nis').val(nis);
                $('#edit_tgl_lahir').val(tgl_lahir);
                $('#edit_kelas_id').val(kelas);
                $('#edit_no_wa').val(wa);
                $('#edit_wa_ortu').val(wa_ortu);
                $('#edit_uid_rfid').val(uid);

                $('#formEditSiswa').attr('action', '{{ url('siswa') }}/' + id);
            });

            // Hapus
            $('.btnHapus').on('click', function () {
                var id = $(this).data('id');
                var nama = $(this).data('nama');
                $('#hapus_nama').text(nama);
                $('#formHapusSiswa').attr('action', '{{ url('siswa') }}/' + id);
            });

            // ==========================
            //  ENROLL LOGIC
            // ==========================
            let enrollSiswaId = null;
            let enrollInterval = null;

            $('.btnEnroll').on('click', function () {
                enrollSiswaId = $(this).data('id');
                $('#enroll_nama').text($(this).data('nama'));

                // Reset UI
                $('#enroll_status').html('');
                $('#uid_wrapper').addClass('hidden');
                $('#enroll_uid').text('');
                $('#btnHapusUID').prop('disabled', true);

                // Check existing UID
                var uid = $(this).data('uid');
                if (uid) {
                    $('#enroll_uid').text(uid);
                    $('#uid_wrapper').removeClass('hidden');
                    $('#btnHapusUID').prop('disabled', false);
                }
            });

            $('#btnMulaiEnroll').on('click', function () {
                if (!enrollSiswaId) return;

                $('#enroll_status').html('<span class="text-brand-500 animate-pulse"><i class="fas fa-spinner fa-spin mr-2"></i>Silakan tempelkan kartu RFID...</span>');

                // Request Enroll
                $.post('{{ url('siswa') }}/' + enrollSiswaId + '/enroll', {
                    _token: '{{ csrf_token() }}'
                }, function (res) {
                    if (res.ok) {
                        startEnrollPolling(enrollSiswaId);
                    } else {
                        $('#enroll_status').html('<span class="text-error-500"><i class="fas fa-times-circle mr-1"></i>Gagal request enrollment.</span>');
                    }
                });
            });

            function startEnrollPolling(id) {
                if (enrollInterval) clearInterval(enrollInterval);
                let counter = 0;

                enrollInterval = setInterval(function () {
                    counter++;
                    if (counter > 20) { // 30 sec timeout
                        clearInterval(enrollInterval);
                        $('#enroll_status').html('<span class="text-warning-500"><i class="fas fa-exclamation-triangle mr-1"></i>Waktu habis. Coba lagi.</span>');
                        return;
                    }

                    $.get('{{ url('siswa') }}/' + id + '/enroll-check', function (res) {
                        if (res.ok && res.uid) {
                            clearInterval(enrollInterval);
                            $('#enroll_uid').text(res.uid);
                            $('#uid_wrapper').removeClass('hidden');
                            $('#btnHapusUID').prop('disabled', false);
                            $('#enroll_status').html('<span class="text-success-500 font-bold"><i class="fas fa-check-circle mr-1"></i> Berhasil! Menyegarkan...</span>');

                            setTimeout(function () { location.reload(); }, 1500);
                        }
                    });
                }, 1500);
            }

            $('#btnHapusUID').on('click', function () {
                if (!enrollSiswaId) return;
                if (!confirm('Hapus UID RFID siswa ini?')) return;

                $.post('{{ url('siswa') }}/' + enrollSiswaId + '/delete-uid', {
                    _token: '{{ csrf_token() }}'
                }, function (res) {
                    if (res.ok) {
                        $('#uid_wrapper').addClass('hidden');
                        $('#enroll_uid').text('');
                        $('#btnHapusUID').prop('disabled', true);
                        $('#enroll_status').html('<span class="text-warning-500">UID dihapus.</span>');
                        setTimeout(function () { location.reload(); }, 1000);
                    }
                });
            });

            // Handle modal close to cancel enrollment
            window.addEventListener('close-modal', function(e) {
                if(e.detail === 'modalEnrollRFID') {
                    if (enrollInterval) clearInterval(enrollInterval);
                    if (enrollSiswaId) {
                        $.post('{{ url('siswa') }}/' + enrollSiswaId + '/enroll-cancel', {
                            _token: '{{ csrf_token() }}'
                        });
                    }
                    enrollSiswaId = null;
                }
                
                if(e.detail === 'modalEnrollFinger') {
                    if (enrollFingerInterval) clearInterval(enrollFingerInterval);
                    if (enrollFingerSiswaId) {
                        $.post('{{ url('siswa') }}/' + enrollFingerSiswaId + '/enroll-finger-cancel', {
                            _token: '{{ csrf_token() }}'
                        });
                    }
                    enrollFingerSiswaId = null;
                }
            });

            // ==========================
            //  FINGERPRINT ENROLL LOGIC
            // ==========================
            let enrollFingerSiswaId = null;
            let enrollFingerInterval = null;

            $('.btnEnrollFinger').on('click', function () {
                enrollFingerSiswaId = $(this).data('id');
                $('#enroll_finger_nama').text($(this).data('nama'));

                // Reset UI
                $('#enroll_finger_status').html('');
                $('#finger_id_wrapper').addClass('hidden');
                $('#enroll_finger_id').text('');
                $('#btnHapusFinger').prop('disabled', true);
                $('#finger_device_id').val('');

                // Check existing Finger ID
                var fingerId = $(this).data('finger');
                if (fingerId) {
                    $('#enroll_finger_id').text(fingerId);
                    $('#finger_id_wrapper').removeClass('hidden');
                    $('#btnHapusFinger').prop('disabled', false);
                }
            });

            $('#btnMulaiEnrollFinger').on('click', function () {
                if (!enrollFingerSiswaId) return;

                var deviceId = $('#finger_device_id').val();
                if (!deviceId) {
                    alert('Pilih device terlebih dahulu!');
                    return;
                }

                $('#enroll_finger_status').html('<span class="text-success-500 animate-pulse"><i class="fas fa-spinner fa-spin mr-2"></i>Silakan tempelkan jari pada sensor...</span>');

                // Request Enroll with device_id
                $.post('{{ url('siswa') }}/' + enrollFingerSiswaId + '/enroll-finger', {
                    _token: '{{ csrf_token() }}',
                    device_id: deviceId
                }, function (res) {
                    if (res.ok) {
                        startFingerEnrollPolling(enrollFingerSiswaId);
                    } else {
                        $('#enroll_finger_status').html('<span class="text-error-500"><i class="fas fa-times-circle mr-1"></i>Gagal request enrollment.</span>');
                    }
                });
            });

            function startFingerEnrollPolling(id) {
                if (enrollFingerInterval) clearInterval(enrollFingerInterval);
                let counter = 0;

                enrollFingerInterval = setInterval(function () {
                    counter++;
                    if (counter > 40) { // 60 sec timeout
                        clearInterval(enrollFingerInterval);
                        $('#enroll_finger_status').html('<span class="text-warning-500"><i class="fas fa-exclamation-triangle mr-1"></i>Waktu habis. Coba lagi.</span>');
                        return;
                    }

                    $.get('{{ url('siswa') }}/' + id + '/enroll-finger-check', function (res) {
                        if (res.ok && res.id_finger && res.status === 'done') {
                            clearInterval(enrollFingerInterval);
                            $('#enroll_finger_id').text(res.id_finger);
                            $('#finger_id_wrapper').removeClass('hidden');
                            $('#btnHapusFinger').prop('disabled', false);
                            $('#enroll_finger_status').html('<span class="text-success-500 font-bold"><i class="fas fa-check-circle mr-1"></i> Berhasil! Menyegarkan...</span>');

                            setTimeout(function () { location.reload(); }, 1500);
                        }
                    });
                }, 1500);
            }

            $('#btnHapusFinger').on('click', function () {
                if (!enrollFingerSiswaId) return;
                if (!confirm('Hapus sidik jari siswa ini dari semua device?')) return;

                $.post('{{ url('siswa') }}/' + enrollFingerSiswaId + '/delete-finger', {
                    _token: '{{ csrf_token() }}'
                }, function (res) {
                    if (res.ok) {
                        $('#finger_id_wrapper').addClass('hidden');
                        $('#enroll_finger_id').text('');
                        $('#btnHapusFinger').prop('disabled', true);
                        $('#enroll_finger_status').html('<span class="text-warning-500">Sidik jari dihapus.</span>');
                        setTimeout(function () { location.reload(); }, 1000);
                    }
                });
            });
        });
        
        // Alpine component for Bulk Actions
        function bulkSiswaActions() {
            return {
                selected: [],
                allIds: {{ json_encode($siswa->pluck('id')->toArray()) }},
                get isAllSelected() {
                    return this.selected.length > 0 && this.selected.length === this.allIds.length;
                },
                toggleAll() {
                    if (this.isAllSelected) {
                        this.selected = [];
                    } else {
                        this.selected = [...this.allIds];
                    }
                },
                bulkDelete() {
                    if(this.selected.length === 0) return;
                    $.ajax({
                        url: '{{ route("siswa.bulk-destroy") }}',
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}',
                            ids: this.selected
                        },
                        success: function(res) {
                            if(res.success) location.reload();
                        },
                        error: function(err) {
                            alert('Gagal menghapus data.');
                        }
                    });
                },
                bulkUpdateKelas(kelasId) {
                    if(this.selected.length === 0 || !kelasId) {
                        alert('Pastikan siswa dan kelas tujuan sudah dipilih.');
                        return;
                    }
                    $.ajax({
                        url: '{{ route("siswa.bulk-update-kelas") }}',
                        type: 'PUT',
                        data: {
                            _token: '{{ csrf_token() }}',
                            ids: this.selected,
                            kelas_id: kelasId
                        },
                        success: function(res) {
                            if(res.success) location.reload();
                        },
                        error: function(err) {
                            alert('Gagal mengubah kelas.');
                        }
                    });
                }
            }
        }
        
        // Alpine component for handling import progress
        function importFormSiswa() {
            return {
                isImporting: false,
                isFinished: false,
                isSuccess: false,
                progress: 0,
                message: '',
                interval: null,
                reset() {
                    this.isImporting = false;
                    this.isFinished = false;
                    this.progress = 0;
                    if(this.$refs.fileInput) this.$refs.fileInput.value = '';
                },
                submitForm() {
                    const fileInput = this.$refs.fileInput;
                    if (!fileInput.files.length) return;

                    this.isImporting = true;
                    this.progress = 0;

                    const formData = new FormData();
                    formData.append('fileExcel', fileInput.files[0]);
                    formData.append('_token', '{{ csrf_token() }}');

                    // Simulate progress for UI (goes up to 90%)
                    this.interval = setInterval(() => {
                        if (this.progress < 90) {
                            // Slow down as it gets higher
                            const increment = Math.max(1, Math.floor((90 - this.progress) / 10));
                            this.progress += increment;
                        }
                    }, 600);

                    fetch('{{ route('siswa.import') }}', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        clearInterval(this.interval);
                        this.progress = 100;
                        setTimeout(() => {
                            this.isImporting = false;
                            this.isFinished = true;
                            this.isSuccess = data.success;
                            this.message = data.message;
                        }, 500);
                    })
                    .catch(error => {
                        clearInterval(this.interval);
                        this.progress = 100;
                        setTimeout(() => {
                            this.isImporting = false;
                            this.isFinished = true;
                            this.isSuccess = false;
                            this.message = 'Terjadi kesalahan sistem saat memproses file.';
                        }, 500);
                    });
                }
            }
        }
    </script>
@endpush