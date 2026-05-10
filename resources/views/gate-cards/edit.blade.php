@extends('layouts.app')

@section('title', 'Edit Kartu Gerbang')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h2 class="text-title-md2 font-semibold text-gray-800 dark:text-white/90">
        Edit Kartu Gerbang
    </h2>
    <a href="{{ route('gate-cards.index') }}" class="inline-flex items-center justify-center gap-2.5 rounded-md bg-gray-500 px-10 py-3 text-center font-medium text-white hover:bg-opacity-90 lg:px-8 xl:px-10 transition">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</div>

<div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
    <div class="border-b border-stroke py-4 px-6.5 dark:border-strokedark">
        <h3 class="font-medium text-black dark:text-white">
            Form Edit Kartu
        </h3>
    </div>
    
    <div class="p-6.5" x-data="{ 
        guruId: '{{ old('guru_id', $gateCard->guru_id ? $gateCard->guru_id : 'lainnya') }}',
        init() {
            if (this.guruId === '') this.guruId = 'lainnya';
        }
    }">
        <form action="{{ route('gate-cards.update', $gateCard->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="mb-4.5">
                <label class="mb-2.5 block text-black dark:text-white">
                    Pemilik Kartu (Hubungkan dengan Karyawan) <span class="text-meta-1">*</span>
                </label>
                <div class="relative z-20 bg-transparent dark:bg-form-input">
                    <select name="guru_id" x-model="guruId" class="relative z-20 w-full appearance-none rounded border border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-form-strokedark dark:bg-form-input dark:focus:border-brand-500 @error('guru_id') border-danger @enderror">
                        <option value="">-- Pilih Karyawan/Guru --</option>
                        <option value="lainnya">[Lainnya / Eksternal]</option>
                        @foreach($gurus as $guru)
                            <option value="{{ $guru->id }}">{{ $guru->nama }}</option>
                        @endforeach
                    </select>
                    <span class="absolute top-1/2 right-4 z-30 -translate-y-1/2">
                        <i class="fas fa-chevron-down text-sm"></i>
                    </span>
                </div>
                @error('guru_id')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4.5" x-show="guruId === 'lainnya' || guruId === ''">
                <label class="mb-2.5 block text-black dark:text-white">
                    Nama Pemegang Kartu <span class="text-meta-1" x-show="guruId === 'lainnya' || guruId === ''">*</span>
                </label>
                <input type="text" name="name" :required="guruId === 'lainnya' || guruId === ''" value="{{ old('name', $gateCard->name) }}" class="w-full rounded border border-stroke bg-transparent py-3 px-5 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-form-strokedark dark:bg-form-input dark:focus:border-brand-500 @error('name') border-danger @enderror" />
                @error('name')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-5.5">
                <label class="mb-2.5 block text-black dark:text-white">
                    UID RFID
                </label>
                <input type="text" name="uid_rfid" value="{{ old('uid_rfid', $gateCard->uid_rfid) }}" readonly class="w-full rounded border border-gray-200 bg-gray-100 py-3 px-5 outline-none dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400 @error('uid_rfid') border-danger @enderror" />
                @error('uid_rfid')
                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="flex w-full justify-center rounded bg-brand-500 p-3 font-medium text-white hover:bg-opacity-90 transition">
                Simpan Perubahan
            </button>
        </form>
    </div>
</div>
@endsection
