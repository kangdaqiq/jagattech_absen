@extends('layouts.app')

@section('title', 'Manajemen Kartu Gerbang')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-gray-800 dark:text-white/90">
            <i class="fas fa-id-badge text-brand-500 mr-2"></i> Manajemen Kartu Gerbang
        </h2>
        <button @click="$dispatch('open-modal', 'modalTambahKartu')"
            class="inline-flex items-center justify-center gap-2.5 rounded-md bg-brand-500 px-6 py-3 text-center font-medium text-white hover:bg-opacity-90 transition">
            <i class="fas fa-plus"></i> Tambah Kartu
        </button>
    </div>

    @if (session('success'))
        <div
            class="mb-4 rounded-lg bg-success/10 border border-success/20 px-4 py-3 text-sm font-medium text-success flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div
            class="mb-4 rounded-lg bg-error-500/10 border border-error-500/20 px-4 py-3 text-sm font-medium text-error-500 flex items-center gap-2">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div class="border-b border-stroke py-4 px-6.5 dark:border-strokedark">
            <h3 class="font-medium text-black dark:text-white">
                Daftar Kartu Gerbang
            </h3>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-2 text-left dark:bg-meta-4">
                        <th class="py-4 px-4 font-medium text-black dark:text-white xl:pl-11">No</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">Nama Kartu</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white">UID RFID</th>
                        <th class="py-4 px-4 font-medium text-black dark:text-white text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gateCards as $card)
                        <tr>
                            <td class="border-b border-[#eee] py-5 px-4 pl-9 dark:border-strokedark xl:pl-11">
                                <p class="text-black dark:text-white">{{ $loop->iteration }}</p>
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                @if($card->guru_id)
                                    <span
                                        class="inline-flex rounded-full bg-brand-500/10 px-3 py-1 text-sm font-medium text-brand-500">
                                        <i class="fas fa-user-tie mr-1 mt-0.5"></i> {{ $card->guru->nama ?? $card->name }}
                                    </span>
                                @else
                                    <p class="text-black dark:text-white">{{ $card->name }}</p>
                                @endif
                            </td>
                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                @if ($card->uid_rfid)
                                    <span class="inline-flex rounded-full bg-success/10 px-3 py-1 text-sm font-medium text-success">
                                        {{ $card->uid_rfid }}
                                    </span>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        Belum terdaftar
                                    </span>
                                @endif
                            </td>

                            <td class="border-b border-[#eee] py-5 px-4 dark:border-strokedark">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- Enroll RFID --}}
                                    <button
                                        class="btnEnroll text-blue-500 hover:text-blue-700 hover:bg-blue-50 p-2 rounded-lg transition"
                                        data-id="{{ $card->id }}" data-nama="{{ $card->name }}" data-uid="{{ $card->uid_rfid }}"
                                        @click="$dispatch('open-modal', 'modalEnrollRFID')" title="Registrasi RFID">
                                        <i class="fas fa-rss"></i>
                                    </button>

                                    {{-- Edit --}}
                                    <button
                                        class="btnEditKartu text-orange-500 hover:text-orange-700 hover:bg-orange-50 dark:hover:bg-orange-500/10 p-2 rounded-lg transition"
                                        data-id="{{ $card->id }}" data-guru-id="{{ $card->guru_id ?? 'lainnya' }}"
                                        data-name="{{ $card->name }}" data-uid="{{ $card->uid_rfid }}"
                                        @click="$dispatch('open-modal', 'modalEditKartu')" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    {{-- Delete --}}
                                    <form action="{{ route('gate-cards.destroy', $card->id) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Yakin ingin menghapus kartu ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-500/10 p-2 rounded-lg transition"
                                            title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if($gateCards->isEmpty())
                        <tr>
                            <td colspan="5"
                                class="border-b border-[#eee] py-8 px-4 dark:border-strokedark text-center text-gray-500 dark:text-gray-400">
                                Belum ada kartu gerbang.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- ========================= MODALS ========================= --}}

    {{-- Modal Tambah Kartu --}}
    <x-ui.modal id="modalTambahKartu" :is-open="false">
        <div class="p-6" x-data="{
            guruId: '',
            init() { if (this.guruId === '') this.guruId = 'lainnya'; }
        }">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Tambah Kartu Gerbang</h3>
                <button @click="open = false"
                    class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="{{ route('gate-cards.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Pemilik Kartu <span class="text-error-500">*</span>
                    </label>
                    <div class="relative">
                        <select name="guru_id" x-model="guruId"
                            class="w-full appearance-none rounded-lg border border-gray-200 bg-transparent px-4 py-2.5 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            <option value="">-- Pilih Karyawan/Guru --</option>
                            <option value="lainnya">[Lainnya / Eksternal]</option>
                            @foreach($gurus as $guru)
                                <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </span>
                    </div>
                </div>

                <div x-show="guruId === 'lainnya' || guruId === ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Nama Pemegang Kartu <span class="text-error-500"
                            x-show="guruId === 'lainnya' || guruId === ''">*</span>
                    </label>
                    <input type="text" name="name" :required="guruId === 'lainnya' || guruId === ''"
                        placeholder="Contoh: Satpam Depan"
                        class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2.5 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white" />
                </div>



                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="open = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">
                        Batal
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-white hover:bg-brand-600 transition">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    {{-- Modal Edit Kartu --}}
    <x-ui.modal id="modalEditKartu" :is-open="false">
        <div class="p-6" x-data="{
            guruId: 'lainnya',
        }">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Edit Kartu Gerbang</h3>
                <button @click="open = false"
                    class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formEditKartu" action="" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Pemilik Kartu <span class="text-error-500">*</span>
                    </label>
                    <div class="relative">
                        <select name="guru_id" id="edit_guru_id" x-model="guruId"
                            class="w-full appearance-none rounded-lg border border-gray-200 bg-transparent px-4 py-2.5 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
                            <option value="">-- Pilih Karyawan/Guru --</option>
                            <option value="lainnya">[Lainnya / Eksternal]</option>
                            @foreach($gurus as $guru)
                                <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </span>
                    </div>
                </div>

                <div x-show="guruId === 'lainnya' || guruId === ''">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Nama Pemegang Kartu <span class="text-error-500"
                            x-show="guruId === 'lainnya' || guruId === ''">*</span>
                    </label>
                    <input type="text" name="name" id="edit_name" :required="guruId === 'lainnya' || guruId === ''"
                        class="w-full rounded-lg border border-gray-200 bg-transparent px-4 py-2.5 outline-none focus:border-brand-500 dark:border-gray-800 dark:bg-gray-900 dark:text-white" />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        UID RFID
                    </label>
                    <input type="text" name="uid_rfid" id="edit_uid_rfid" readonly
                        class="w-full rounded-lg border border-gray-200 bg-gray-100 px-4 py-2.5 outline-none dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400" />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="open = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">
                        Batal
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-white hover:bg-brand-600 transition">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    {{-- Modal Enroll RFID --}}
    <x-ui.modal id="modalEnrollRFID" :is-open="false">
        <div class="p-6 text-center">
            <div class="flex justify-end mb-2">
                <button @click="open = false"
                    class="text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"><i
                        class="fas fa-times"></i></button>
            </div>
            <div
                class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-500 dark:bg-brand-500/15">
                <i class="fas fa-id-card fa-2x"></i>
            </div>
            <h3 class="mb-2 text-2xl font-bold text-gray-800 dark:text-white/90">Registrasi RFID</h3>
            <h5 id="enroll_nama" class="font-medium text-gray-600 dark:text-gray-400 mb-6"></h5>

            <div id="uid_wrapper"
                class="hidden mb-4 rounded-lg bg-success-50 p-3 text-success-700 dark:bg-success-500/15 dark:text-success-500">
                UID Terdaftar: <strong id="enroll_uid" class="text-lg"></strong>
            </div>

            <div id="enroll_status" class="mb-6 h-10 flex items-center justify-center"></div>

            <div class="flex flex-col gap-3">
                <button type="button"
                    class="rounded-lg bg-brand-500 p-3 font-medium text-white hover:bg-brand-600 transition"
                    id="btnMulaiEnroll">
                    <i class="fas fa-rss mr-1"></i> Mulai Scan Kartu
                </button>
                <button type="button"
                    class="rounded-lg bg-error-500 p-3 font-medium text-white hover:bg-error-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    id="btnHapusUID" disabled>
                    <i class="fas fa-trash mr-1"></i> Hapus UID
                </button>
            </div>
        </div>
    </x-ui.modal>

@endsection

@push('scripts')
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script>
        $(document).ready(function () {

            // ===== Edit Kartu Modal =====
            document.querySelectorAll('.btnEditKartu').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.dataset.id;
                    const guruId = this.dataset.guruId;
                    const name = this.dataset.name;
                    const uid = this.dataset.uid;

                    // Set form action URL
                    document.getElementById('formEditKartu').action = '/gate-cards/' + id;

                    // Set select value (via Alpine x-model, we need to set the DOM select directly too)
                    const sel = document.getElementById('edit_guru_id');
                    sel.value = guruId || 'lainnya';
                    // Trigger Alpine update
                    sel.dispatchEvent(new Event('change'));

                    document.getElementById('edit_name').value = name || '';
                    document.getElementById('edit_uid_rfid').value = uid || '';
                });
            });

            // ===== ENROLL LOGIC =====
            let enrollCardId = null;
            let enrollInterval = null;

            $('.btnEnroll').on('click', function () {
                enrollCardId = $(this).data('id');
                $('#enroll_nama').text($(this).data('nama'));
                $('#enroll_status').html('');
                $('#uid_wrapper').addClass('hidden');
                $('#enroll_uid').text('');
                $('#btnHapusUID').prop('disabled', true);

                var uid = $(this).data('uid');
                if (uid) {
                    $('#enroll_uid').text(uid);
                    $('#uid_wrapper').removeClass('hidden');
                    $('#btnHapusUID').prop('disabled', false);
                }
            });

            $('#btnMulaiEnroll').on('click', function () {
                if (!enrollCardId) return;
                $('#enroll_status').html('<span class="text-brand-500 animate-pulse"><i class="fas fa-spinner fa-spin mr-2"></i>Silakan tempelkan kartu RFID...</span>');

                $.post('{{ url('gate-cards') }}/' + enrollCardId + '/enroll', {
                    _token: '{{ csrf_token() }}'
                }, function (res) {
                    if (res.ok) {
                        startEnrollPolling(enrollCardId);
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
                    if (counter > 20) {
                        clearInterval(enrollInterval);
                        $('#enroll_status').html('<span class="text-warning-500"><i class="fas fa-exclamation-triangle mr-1"></i>Waktu habis. Coba lagi.</span>');
                        return;
                    }
                    $.get('{{ url('gate-cards') }}/' + id + '/enroll-check', function (res) {
                        if (res.ok && res.uid) {
                            clearInterval(enrollInterval);
                            $('#enroll_uid').text(res.uid);
                            $('#uid_wrapper').removeClass('hidden');
                            $('#enroll_status').html('<span class="text-success-500"><i class="fas fa-check-circle mr-1"></i>Kartu berhasil didaftarkan! Halaman akan dimuat ulang...</span>');
                            setTimeout(() => location.reload(), 1500);
                        } else if (res.error) {
                            clearInterval(enrollInterval);
                            $('#enroll_status').html('<span class="text-error-500"><i class="fas fa-times-circle mr-1"></i>Gagal: ' + res.error + '</span>');
                        }
                    });
                }, 1500);
            }

            document.addEventListener('alpine:initialized', () => {
                window.addEventListener('modal-closed', (e) => {
                    if (e.detail === 'modalEnrollRFID') {
                        if (enrollInterval) {
                            clearInterval(enrollInterval);
                            if (enrollCardId) {
                                $.post('{{ url('gate-cards') }}/' + enrollCardId + '/enroll-cancel', {
                                    _token: '{{ csrf_token() }}'
                                });
                            }
                        }
                    }
                });
            });

            $('#btnHapusUID').on('click', function () {
                if (!enrollCardId) return;
                if (confirm('Yakin ingin menghapus UID kartu ini?')) {
                    $.post('{{ url('gate-cards') }}/' + enrollCardId + '/delete-uid', {
                        _token: '{{ csrf_token() }}'
                    }, function (res) {
                        if (res.ok) {
                            $('#uid_wrapper').addClass('hidden');
                            $('#enroll_uid').text('');
                            $('#btnHapusUID').prop('disabled', true);
                            $('#enroll_status').html('<span class="text-success-500"><i class="fas fa-check-circle mr-1"></i>UID berhasil dihapus. Halaman akan dimuat ulang...</span>');
                            setTimeout(() => location.reload(), 1000);
                        }
                    });
                }
            });
        });
    </script>
@endpush