<!DOCTYPE html>
<html>
<head>
    <title>Laporan Absensi</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        th { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2, .header h3 { margin: 0; }
        .meta { margin-bottom: 10px; }
        .footer { margin-top: 30px; text-align: right; }
        .signature { margin-top: 50px; text-align: right; padding-right: 30px; }
    </style>
</head>
<body>
    <div class="header">
        @php
            $hasKop = !empty($kopSurat);
            $kopPath = null;
            if ($hasKop) {
                if (\Illuminate\Support\Str::startsWith($kopSurat, 'schools/')) {
                    $kopPath = storage_path('app/public/' . $kopSurat);
                } else {
                    $kopPath = public_path('img/' . $kopSurat);
                }
            } else {
                $kopPath = public_path('img/default_kop.png');
            }
        @endphp

        @if($kopPath && file_exists($kopPath))
            <img src="{{ $kopPath }}" style="width: 100%; max-height: 120px; object-fit: contain; margin-bottom: 10px;">
        @else
            <h2>{{ $schoolName }}</h2>
            <p style="margin: 0; font-size: 10px;">{{ $schoolAddress }}</p>
            <hr style="border: 1px double #000; margin-top: 10px;">
        @endif
        
        <h3 style="margin-top: 15px;">Laporan Rekapitulasi Absensi Siswa</h3>
    </div>

    <div class="meta">
        <strong>Kelas:</strong> {{ $kelas ? $kelas->nama_kelas : 'Semua Kelas' }}<br>
        <strong>Periode:</strong> {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Nama Siswa</th>
                <th width="10%">Hadir</th>
                <th width="10%">Izin</th>
                <th width="10%">Sakit</th>
                <th width="10%">Alpha</th>
                <th width="10%">Bolos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rekap as $r)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td style="text-align: left; padding-left: 10px;">{{ $r['nama'] }}</td>
                <td>{{ $r['hadir'] }}</td>
                <td>{{ $r['izin'] }}</td>
                <td>{{ $r['sakit'] }}</td>
                <td>{{ $r['alpha'] }}</td>
                <td>{{ $r['bolos'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f2f2f2;">
                <td colspan="2">TOTAL</td>
                <td>{{ collect($rekap)->sum('hadir') }}</td>
                <td>{{ collect($rekap)->sum('izin') }}</td>
                <td>{{ collect($rekap)->sum('sakit') }}</td>
                <td>{{ collect($rekap)->sum('alpha') }}</td>
                <td>{{ collect($rekap)->sum('bolos') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px; text-align: right; font-size: 12px; padding-right: 40px;">
        {{ $signatureLocation }}, {{ now()->isoFormat('DD MMMM Y') }}
    </div>

    <table style="width: 100%; margin-top: 30px; border: none;">
        <tr>
            <td style="width: 50%; text-align: center; border: none; vertical-align: top; padding: 0 20px;">
                <div style="font-size: 12px;">Diketahui Oleh,</div>
                <div style="font-size: 12px; font-weight: bold;">Kepala Sekolah</div>
                <br><br><br><br>
                <div style="font-size: 12px; border-top: 1px solid #000; display: inline-block; padding-top: 4px; min-width: 180px;">
                    {{ $namaKepsek ?: '.............................' }}
                </div>
                @if($nipKepsek)
                    <div style="font-size: 11px;">NIP. {{ $nipKepsek }}</div>
                @endif
            </td>
            <td style="width: 50%; text-align: center; border: none; vertical-align: top; padding: 0 20px;">
                <div style="font-size: 12px;">Dibuat Oleh,</div>
                <div style="font-size: 12px; font-weight: bold;">Waka Kesiswaan</div>
                <br><br><br><br>
                <div style="font-size: 12px; border-top: 1px solid #000; display: inline-block; padding-top: 4px; min-width: 180px;">
                    {{ $namaWaka ?: '.............................' }}
                </div>
                @if($nipWaka)
                    <div style="font-size: 11px;">NIP. {{ $nipWaka }}</div>
                @endif
            </td>
        </tr>
    </table>

</body>
</html>
