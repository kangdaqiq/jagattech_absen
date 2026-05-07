<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Rekap Absensi Guru</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .stats {
            margin: 15px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
        }
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
            <h2>{{ $schoolName ?? 'SMK Negeri Contoh' }}</h2>
            <p>{{ $schoolAddress ?? 'Alamat Sekolah Belum Diatur' }}</p>
            <hr>
        @endif
        <h3>REKAP ABSENSI GURU</h3>
        <p>Periode: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} -
            {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
    </div>

    <div class="stats">
        <strong>Statistik:</strong>
        Total: {{ $stats['total'] }} |
        Hadir: {{ $stats['hadir'] }} |
        Tidak Hadir: {{ $stats['tidak_hadir'] }}
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="10%">Tanggal</th>
                <th width="20%">Guru</th>
                <th width="20%">Mata Pelajaran</th>
                <th width="15%">Kelas</th>
                <th width="15%">Jam Mengajar</th>
                <th width="10%">Status</th>
                <th width="10%">Waktu Hadir</th>
            </tr>
        </thead>
        <tbody>
            @foreach($absensi as $a)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ \Carbon\Carbon::parse($a->tanggal)->format('d/m/Y') }}</td>
                    <td>{{ $a->guru->nama ?? '-' }}</td>
                    <td>{{ $a->jadwal->mapel->nama_mapel ?? '-' }}</td>
                    <td>{{ $a->jadwal->kelas->nama_kelas ?? '-' }}</td>
                    <td>
                        @if($a->jadwal)
                            {{ substr($a->jadwal->jam_mulai, 0, 5) }} - {{ substr($a->jadwal->jam_selesai, 0, 5) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($a->status == 'Hadir')
                            <span class="badge-success">Hadir</span>
                        @else
                            <span class="badge-danger">Tidak Hadir</span>
                        @endif
                    </td>
                    <td>{{ $a->waktu_hadir ? \Carbon\Carbon::parse($a->waktu_hadir)->format('H:i') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 30px; font-size: 10px; color: #666;">
        <p>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>

</html>